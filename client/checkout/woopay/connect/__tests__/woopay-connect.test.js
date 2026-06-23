/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/woopay/connect/connect-utils', () => ( {
	getConnectIframeInjectedState: jest.fn().mockReturnValue( 'INJECTED' ),
	getPostMessageTimeout: jest.fn().mockReturnValue( 5000 ),
	setConnectIframeInjectedState: jest.fn(),
	INJECTED_STATE: {
		NOT_INJECTED: 'NOT_INJECTED',
		INJECTING: 'INJECTING',
		INJECTED: 'INJECTED',
	},
} ) );

jest.mock( 'wcpay/checkout/woopay/connect/woopay-connect-iframe', () => ( {
	WooPayConnectIframe: () => null,
} ) );

jest.mock( 'react-dom/client', () => ( {
	createRoot: jest.fn( () => ( { render: jest.fn() } ) ),
} ) );

describe( 'WoopayConnect origin validation', () => {
	let WoopayConnect;

	beforeEach( () => {
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'woopayHost' ) return 'https://pay.woo.com';
			return undefined;
		} );

		// Isolate module to get a fresh class each test.
		jest.isolateModules( () => {
			WoopayConnect = require( '../woopay-connect' ).default;
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'accepts messages from the exact WooPay origin', () => {
		const connect = new WoopayConnect();
		connect.callbackFn = jest.fn();

		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://pay.woo.com',
				data: { action: 'test', value: true },
			} )
		);

		expect( connect.callbackFn ).toHaveBeenCalledWith( {
			action: 'test',
			value: true,
		} );

		connect.detachMessageListener();
	} );

	it( 'accepts messages when woopayHost includes a path', () => {
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'woopayHost' ) return 'https://pay.woo.com/woopay';
			return undefined;
		} );

		const connect = new WoopayConnect();
		connect.callbackFn = jest.fn();

		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://pay.woo.com',
				data: { action: 'test' },
			} )
		);

		expect( connect.callbackFn ).toHaveBeenCalled();

		connect.detachMessageListener();
	} );

	it( 'rejects messages from a prefix-matching origin', () => {
		const connect = new WoopayConnect();
		connect.callbackFn = jest.fn();

		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://pay.woo.co',
				data: { action: 'test' },
			} )
		);

		expect( connect.callbackFn ).not.toHaveBeenCalled();

		connect.detachMessageListener();
	} );

	it( 'rejects messages from a different origin entirely', () => {
		const connect = new WoopayConnect();
		connect.callbackFn = jest.fn();

		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://evil.com',
				data: { action: 'test' },
			} )
		);

		expect( connect.callbackFn ).not.toHaveBeenCalled();

		connect.detachMessageListener();
	} );

	it( 'rejects messages from a subdomain of the WooPay host', () => {
		const connect = new WoopayConnect();
		connect.callbackFn = jest.fn();

		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://evil.pay.woo.com',
				data: { action: 'test' },
			} )
		);

		expect( connect.callbackFn ).not.toHaveBeenCalled();

		connect.detachMessageListener();
	} );
} );
