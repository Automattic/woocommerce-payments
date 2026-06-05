/**
 * Internal dependencies
 */
import WCPayAPI from 'wcpay/checkout/api';
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
	test( 'does not initialize woopay if already requesting', async () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockImplementation( ( key ) => {
			const mockProperties = {
				initWooPayNonce: 'foo',
				order_id: 1,
				key: 'testkey',
				billing_email: 'test@example.com',
			};
			return mockProperties[ key ];
		} );

		const api = new WCPayAPI( {}, request );
		api.isWooPayRequesting = true;
		await initWooPay( api, 'foo@bar.com', 'qwerty123' );

		expect( request ).not.toHaveBeenCalled();
		expect( api.isWooPayRequesting ).toBe( true );
	} );

	test( 'initializes woopay using config params', async () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
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

		const api = new WCPayAPI( {}, request );
		await initWooPay( api, 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			appearance: mockAppearance,
			email: 'foo@bar.com',
			user_session: 'qwerty123',
			order_id: 1,
			key: 'testkey',
			billing_email: 'test@example.com',
		} );
		expect( api.isWooPayRequesting ).toBe( false );
	} );

	test( 'WooPay should not support global theme styles', async () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockImplementation( ( key ) => {
			const mockProperties = {
				initWooPayNonce: 'foo',
				isWooPayGlobalThemeSupportEnabled: false,
			};
			return mockProperties[ key ];
		} );

		const api = new WCPayAPI( {}, request );
		await initWooPay( api, 'foo@bar.com', 'qwerty123' );

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
