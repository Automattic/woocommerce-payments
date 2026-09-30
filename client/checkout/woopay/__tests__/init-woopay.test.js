/**
 * Internal dependencies
 */
import { initWooPay } from 'wcpay/checkout/woopay/init-woopay';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ).finally( () => {} ) )
);
jest.mock( 'wcpay/utils/express-checkout', () => ( {
	buildAjaxURL: jest.fn(),
	getExpressCheckoutConfig: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
	getUPEConfig: jest.fn(),
} ) );

const mockAppearance = {
	rules: {
		'.Block': {},
		'.Input': {},
		'.Input--invalid': {},
		'.Label': {},
		'.Label--resting': {},
		'.Tab': {},
		'.Tab--selected': {},
		'.Tab:hover': {},
		'.TabIcon--selected': {
			color: undefined,
		},
		'.TabIcon:hover': {
			color: undefined,
		},
		'.Text': {},
		'.Text--redirect': {},
		'.Heading': {},
		'.Button': {},
		'.Link': {},
		'.Container': {},
		'.Footer': {},
		'.Footer-link': {},
		'.Header': {},
	},
	theme: 'stripe',
	variables: {
		colorBackground: '#ffffff',
		colorText: undefined,
		fontFamily: undefined,
		fontSizeBase: undefined,
	},
	labels: 'above',
};

describe( 'initWooPay', () => {
	beforeEach( () => {
		request.mockClear();
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
	} );

	// The in-flight guard is module-level state, so we simulate a pending request rather
	// than poking an attribute: the first call holds the flag, the second should bail out.
	test( 'skips the request while one is already in flight', async () => {
		getConfig.mockImplementation(
			( key ) => ( { initWooPayNonce: 'foo' }[ key ] )
		);

		let resolveInFlight;
		request.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveInFlight = resolve;
			} )
		);

		const inFlight = initWooPay( request, 'foo@bar.com', 'qwerty123' );
		const second = initWooPay( request, 'foo@bar.com', 'qwerty123' );

		expect( second ).toBeUndefined();
		expect( request ).toHaveBeenCalledTimes( 1 );

		// Let the in-flight request settle so the guard resets for the next test.
		resolveInFlight( {} );
		await inFlight;
	} );

	test( 'initializes woopay using config params', async () => {
		getConfig.mockImplementation( ( key ) => {
			const mockProperties = {
				initWooPayNonce: 'foo',
				order_id: 1,
				key: 'testkey',
				billing_email: 'test@example.com',
				isWooPayGlobalThemeSupportEnabled: true,
				woopayAppearance: mockAppearance,
			};
			return mockProperties[ key ];
		} );

		await initWooPay( request, 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			appearance: mockAppearance,
			email: 'foo@bar.com',
			user_session: 'qwerty123',
			order_id: 1,
			key: 'testkey',
			billing_email: 'test@example.com',
		} );
	} );

	test( 'WooPay should not support global theme styles', async () => {
		getConfig.mockImplementation( ( key ) => {
			const mockProperties = {
				initWooPayNonce: 'foo',
				isWooPayGlobalThemeSupportEnabled: false,
			};
			return mockProperties[ key ];
		} );

		await initWooPay( request, 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			appearance: null,
			font_rules: null,
			email: 'foo@bar.com',
			user_session: 'qwerty123',
			order_id: undefined,
			key: undefined,
			billing_email: undefined,
		} );
	} );
} );
