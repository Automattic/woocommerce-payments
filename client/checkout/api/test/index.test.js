/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';
import {
	buildAjaxURL,
	getExpressCheckoutAjaxURL,
	getExpressCheckoutConfig,
} from 'wcpay/utils/express-checkout';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ).finally( () => {} ) )
);
jest.mock( 'wcpay/utils/express-checkout', () => ( {
	buildAjaxURL: jest.fn(),
	getExpressCheckoutAjaxURL: jest.fn(),
	getExpressCheckoutConfig: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

const mockAppearance = {
	rules: {
		'.Block': {},
		'.Input': {},
		'.Input--invalid': {},
		'.Label': {},
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

describe( 'WCPayAPI', () => {
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
		await api.initWooPay( 'foo@bar.com', 'qwerty123' );

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
			};
			return mockProperties[ key ];
		} );

		const api = new WCPayAPI( {}, request );
		await api.initWooPay( 'foo@bar.com', 'qwerty123' );

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

	test( 'express checkout pay for order is initialized correctly', async () => {
		getExpressCheckoutAjaxURL.mockReturnValue( 'https://example.org/' );
		getExpressCheckoutConfig.mockReturnValue( { pay_for_order: '1234' } );

		const api = new WCPayAPI( {}, request );
		await api.expressCheckoutECEPayForOrder( '12', { foo: 'bar' } );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: '1234',
			order: '12',
			foo: 'bar',
		} );
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
		await api.initWooPay( 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			appearance: null,
			email: 'foo@bar.com',
			user_session: 'qwerty123',
		} );
	} );
} );
