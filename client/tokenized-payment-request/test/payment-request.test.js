/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addAction, applyFilters, doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import PaymentRequestCartApi from '../cart-api';
import WooPaymentsPaymentRequest from '../payment-request';
import { trackPaymentRequestButtonLoad } from '../tracking';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '../tracking', () => ( {
	setPaymentRequestBranding: () => null,
	trackPaymentRequestButtonClick: () => null,
	trackPaymentRequestButtonLoad: jest.fn(),
} ) );

jest.mock( '../button-ui', () => ( {
	showButton: () => null,
	blockButton: () => null,
	unblockButton: () => null,
} ) );
jest.mock( '../debounce', () => ( wait, func ) =>
	function () {
		func.apply( this, arguments );
	}
);

const jQueryMock = ( selector ) => {
	if ( typeof selector === 'function' ) {
		return selector( jQueryMock );
	}

	return {
		on: ( event, callbackOrSelector, callback2 ) =>
			addAction(
				`payment-request-test.jquery-event.${ selector }${
					typeof callbackOrSelector === 'string'
						? `.${ callbackOrSelector }`
						: ''
				}.${ event }`,
				'tests',
				typeof callbackOrSelector === 'string'
					? callback2
					: callbackOrSelector
			),
		val: () => null,
		is: () => null,
		remove: () => null,
	};
};
jQueryMock.blockUI = () => null;

describe( 'WooPaymentsPaymentRequest', () => {
	let wcpayApi;

	beforeEach( () => {
		global.$ = jQueryMock;
		global.jQuery = jQueryMock;
		global.wcpayPaymentRequestParams = {
			nonce: {
				store_api_nonce: 'global_store_api_nonce',
			},
			button_context: 'cart',
			checkout: {
				needs_payer_phone: true,
				country_code: 'US',
				currency_code: 'usd',
			},
			total_label: 'wcpay.test (via WooCommerce)',
			button: { type: 'default', theme: 'dark', height: '48' },
		};
		wcpayApi = {
			getStripe: () => ( {
				paymentRequest: () => ( {
					update: () => null,
					canMakePayment: () => ( { googlePay: true } ),
					on: ( event, callback ) =>
						addAction(
							`payment-request-test.registered-action.${ event }`,
							'tests',
							callback
						),
				} ),
				elements: () => ( {
					create: () => ( { on: () => null } ),
				} ),
			} ),
		};
	} );

	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'should initialize the Stripe payment request, fire initial tracking, and attach event listeners', async () => {
		const headers = new Headers();
		headers.append( 'Nonce', 'nonce-value' );

		apiFetch.mockResolvedValue( {
			headers: headers,
			json: () =>
				Promise.resolve( {
					needs_shipping: false,
					totals: {
						currency_code: 'USD',
						total_price: '20',
						total_tax: '0',
						total_shipping: '5',
					},
					items: [
						{ name: 'Shirt', quantity: 1, prices: { price: '15' } },
					],
				} ),
		} );
		const paymentRequestAvailabilityCallback = jest.fn();
		addAction(
			'wcpay.payment-request.availability',
			'test',
			paymentRequestAvailabilityCallback
		);

		const cartApi = new PaymentRequestCartApi();
		const paymentRequest = new WooPaymentsPaymentRequest( {
			wcpayApi: wcpayApi,
			paymentRequestCartApi: cartApi,
		} );

		expect( paymentRequestAvailabilityCallback ).not.toHaveBeenCalled();
		expect( trackPaymentRequestButtonLoad ).not.toHaveBeenCalled();

		await paymentRequest.init();

		expect( paymentRequestAvailabilityCallback ).toHaveBeenCalledTimes( 1 );
		expect( paymentRequestAvailabilityCallback ).toHaveBeenCalledWith(
			expect.objectContaining( { paymentRequestType: 'google_pay' } )
		);
		expect( trackPaymentRequestButtonLoad ).toHaveBeenCalledWith( 'cart' );

		await applyFilters(
			'wcpay.payment-request.update-button-data',
			Promise.resolve()
		);
		expect( paymentRequestAvailabilityCallback ).toHaveBeenCalledTimes( 1 );

		// firing this should initialize the button again.
		doAction( 'payment-request-test.registered-action.cancel' );

		await applyFilters(
			'wcpay.payment-request.update-button-data',
			Promise.resolve()
		);
		expect( paymentRequestAvailabilityCallback ).toHaveBeenCalledTimes( 2 );
	} );
} );
