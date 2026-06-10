/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DynamicButtonContainer from '../dynamic-button-container';
import { createPaymentCredential } from '../../../utils/payment-credentials';
import { validateElements } from 'wcpay/checkout/utils/validate-elements';

let mockCartData;
let mockElementsProps;
let mockEceProps;

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn( ( props ) => {
		mockElementsProps = props;
		return props.children;
	} ),
	ExpressCheckoutElement: jest.fn( ( props ) => {
		mockEceProps = props;
		return <div data-testid="express-checkout-element" />;
	} ),
	useStripe: () => ( {} ),
	useElements: () => ( { submit: jest.fn() } ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: () => ( {
		getCartData: () => mockCartData,
	} ),
	useSelect: ( callback ) =>
		callback( () => ( {
			getCartData: () => mockCartData,
		} ) ),
} ) );

jest.mock( '../../../utils/payment-credentials', () => ( {
	createPaymentCredential: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/validate-elements', () => ( {
	validateElements: jest.fn().mockResolvedValue( undefined ),
} ) );

const getBaseProps = () => ( {
	expressPaymentMethod: 'googlePay',
	expressPaymentType: 'google_pay',
	stripePaymentMethodType: 'card',
	gatewayId: 'woocommerce_payments_google_pay',
	api: {
		loadStripeForExpressCheckout: jest.fn().mockResolvedValue( {} ),
	},
	validate: jest.fn().mockResolvedValue( { hasError: false } ),
	onSubmit: jest.fn(),
	billing: {
		cartTotal: { value: 2399 },
		cartTotalItems: [],
		currency: { code: 'USD', minorUnit: 2 },
	},
	shippingData: { needsShipping: false },
	eventRegistration: {
		onPaymentSetup: jest.fn( () => () => {} ),
	},
	emitResponse: {
		responseTypes: { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' },
	},
} );

const getRegisteredPaymentSetupCallback = ( props ) =>
	props.eventRegistration.onPaymentSetup.mock.calls[ 0 ][ 0 ];

describe( 'DynamicButtonContainer', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockElementsProps = undefined;
		mockEceProps = undefined;
		mockCartData = {
			items: [
				{
					name: 'A product',
					quantity: 1,
					prices: { price: '2399', currency_minor_unit: 2 },
					totals: {
						line_subtotal: '2399',
						line_subtotal_tax: '0',
						currency_minor_unit: 2,
					},
				},
			],
			totals: {
				total_shipping: '0',
				total_tax: '0',
				currency_minor_unit: 2,
			},
			extensions: {},
		};
		window.wcpayExpressCheckoutParams = {
			checkout: {
				stripe_minor_unit: 2,
				display_prices_with_tax: false,
				needs_payer_phone: false,
			},
			flags: {
				isEceUsingConfirmationTokens: true,
			},
			store_name: 'Test Store',
		};
		window.wcpayFraudPreventionToken = 'fraud-token';
	} );

	it( 'renders nothing in the editor', () => {
		const { container } = render(
			<DynamicButtonContainer { ...getBaseProps() } isEditor />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'builds the Elements options in payment mode with the payment method type', () => {
		render( <DynamicButtonContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( {
				mode: 'payment',
				amount: 2399,
				currency: 'usd',
				paymentMethodTypes: [ 'card' ],
			} )
		);
	} );

	it( 'passes null setupFutureUsage when the cart has no subscription', () => {
		render( <DynamicButtonContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( { setupFutureUsage: null } )
		);
	} );

	it( 'passes off_session setupFutureUsage when the cart contains a subscription', () => {
		mockCartData.extensions = {
			subscriptions: [ { billing_period: 'month' } ],
		};

		render( <DynamicButtonContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( { setupFutureUsage: 'off_session' } )
		);
	} );

	it( 'passes manual captureMethod when manual capture is enabled', () => {
		window.wcpayExpressCheckoutParams.is_manual_capture = true;

		render( <DynamicButtonContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( { captureMethod: 'manual' } )
		);
	} );

	describe( 'click handling', () => {
		it( 'does not resolve the click event when form validation fails', async () => {
			const props = getBaseProps();
			props.validate = jest.fn().mockResolvedValue( { hasError: true } );
			render( <DynamicButtonContainer { ...props } /> );

			const event = { resolve: jest.fn() };
			await mockEceProps.onClick( event );

			expect( event.resolve ).not.toHaveBeenCalled();
		} );

		it( 'resolves the click event with the cart line items', async () => {
			render( <DynamicButtonContainer { ...getBaseProps() } /> );

			const event = { resolve: jest.fn() };
			await mockEceProps.onClick( event );

			expect( event.resolve ).toHaveBeenCalledWith(
				expect.objectContaining( {
					business: { name: 'Test Store' },
					emailRequired: true,
					lineItems: [ { name: 'A product', amount: 2399 } ],
				} )
			);
		} );
	} );

	describe( 'onPaymentSetup', () => {
		it( 'returns the confirmation token in the payment data', async () => {
			createPaymentCredential.mockResolvedValue( {
				id: 'ctoken_123',
				type: 'confirmation_token',
			} );
			const props = getBaseProps();
			render( <DynamicButtonContainer { ...props } /> );

			const result = await getRegisteredPaymentSetupCallback( props )();

			expect( result ).toEqual( {
				type: 'success',
				meta: {
					paymentMethodData: {
						payment_method: 'woocommerce_payments_google_pay',
						'wcpay-confirmation-token': 'ctoken_123',
						express_payment_type: 'google_pay',
						'wcpay-express-payment-method-types': JSON.stringify( [
							'card',
						] ),
						'wcpay-fraud-prevention-token': 'fraud-token',
					},
				},
			} );
		} );

		it( 'returns the payment method id when not using confirmation tokens', async () => {
			createPaymentCredential.mockResolvedValue( {
				id: 'pm_123',
				type: 'payment_method',
			} );
			const props = getBaseProps();
			render( <DynamicButtonContainer { ...props } /> );

			const result = await getRegisteredPaymentSetupCallback( props )();

			expect( result.meta.paymentMethodData ).toEqual(
				expect.objectContaining( {
					'wcpay-payment-method': 'pm_123',
				} )
			);
		} );

		it( 'returns an error response when credential creation fails', async () => {
			createPaymentCredential.mockRejectedValue(
				new Error( 'declined' )
			);
			const props = getBaseProps();
			render( <DynamicButtonContainer { ...props } /> );

			const result = await getRegisteredPaymentSetupCallback( props )();

			expect( result ).toEqual( {
				type: 'error',
				message: 'declined',
			} );
		} );

		it( 'returns an error response when elements validation fails', async () => {
			validateElements.mockRejectedValue(
				new Error( 'incomplete fields' )
			);
			const props = getBaseProps();
			render( <DynamicButtonContainer { ...props } /> );

			const result = await getRegisteredPaymentSetupCallback( props )();

			expect( result ).toEqual( {
				type: 'error',
				message: 'incomplete fields',
			} );
		} );
	} );
} );
