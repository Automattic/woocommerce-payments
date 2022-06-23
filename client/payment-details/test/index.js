/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import PaymentDetailsPage from '../';

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( { setIsMatching: jest.fn() } ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
	useSelect: jest.fn(),
} ) );

useSelect.mockImplementation( ( cb ) =>
	cb(
		jest.fn().mockReturnValue( {
			getCharge: jest.fn().mockReturnValue( {
				id: 'ch_mock',
				amount: 1500,
				application_fee_amount: 74,
				balance_transaction: {
					amount: 1500,
					currency: 'usd',
					fee: 74,
				},
				billing_details: {
					address: {
						city: 'City',
						country: 'US',
						line1: 'Line 1 Street',
						line2: 'Line 2',
						postal_code: '99999',
						state: 'CA',
					},
					email: 'email@example.com',
					name: 'First Name',
					phone: '1-000-000-0000',
					formatted_address:
						'Line 1 Street<br/>Line 2<br/>City, CA 99999',
				},
				captured: true,
				created: 1655921807,
				currency: 'usd',
				customer: 'cus_mock',
				dispute: null,
				disputed: false,
				paid: true,
				payment_intent: 'pi_mock',
				payment_method: 'pm_mock',
				payment_method_details: {
					card: {
						brand: 'visa',
						checks: {
							address_line1_check: 'pass',
							address_postal_code_check: 'pass',
							cvc_check: 'pass',
						},
						country: 'US',
						exp_month: 12,
						exp_year: 2099,
						fingerprint: 'fingerprint',
						funding: 'credit',
						last4: '4242',
						network: 'visa',
					},
					type: 'card',
				},
				refunded: false,
				refunds: {},
				status: 'succeeded',
			} ),
			isResolving: jest.fn().mockReturnValue( false ),
			getChargeError: jest.fn().mockReturnValue( null ),
			getPaymentIntent: jest.fn().mockReturnValue( {
				id: 'pi_mock',
				charge: {
					id: 'ch_mock',
				},
			} ),
			getPaymentIntentError: jest.fn().mockReturnValue( null ),
			getTimeline: jest.fn().mockReturnValue( {} ),
			getTimelineError: jest.fn().mockReturnValue( null ),
		} )
	)
);

global.wcSettings = {
	countries: {
		US: 'United States of America',
	},
};

global.wcpaySettings = {
	featureFlags: {
		paymentTimeline: true,
	},
	zeroDecimalCurrencies: [ 'usd' ],
	connect: {
		country: 'US',
		availableCountries: {
			US: 'United States (US)',
		},
	},
	currencyData: {
		US: {
			code: 'USD',
			symbol: '$',
			symbolPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
		},
	},
};

describe( 'Payment details page', () => {
	const { location } = window;

	const chargeQuery = { id: 'ch_mock' };
	const paymentIntentQuery = { id: 'pi_mock' };
	const redirectUrl =
		'admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=pi_mock';

	beforeEach( () => {
		delete window.location;
		window.location = { href: 'http://example.com' };
	} );

	afterAll( () => {
		window.location = location;
	} );

	it( 'should match the snapshot - Charge', () => {
		const { container } = render(
			<PaymentDetailsPage query={ chargeQuery } />
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should redirect from ch_mock to pi_mock', () => {
		render( <PaymentDetailsPage query={ chargeQuery } /> );

		expect( window.location.href ).toEqual( redirectUrl );
	} );

	it( 'should not redirect with a payment intent ID as query param', () => {
		const { href } = window.location;

		render( <PaymentDetailsPage query={ paymentIntentQuery } /> );

		expect( window.location.href ).toEqual( href );
	} );
} );
