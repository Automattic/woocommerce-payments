/**
 * Internal dependencies
 */
import WooPayUserConnect from '../user-connect';

// Mock the base class to avoid iframe injection.
jest.mock( '../woopay-connect', () => {
	return class MockWoopayConnect {
		iframePostMessage = Promise.resolve( jest.fn() );
		listeners = {};
		removeMessageListener = jest.fn();

		constructor() {
			this.listeners = {};
		}

		attachMessageListener() {
			return jest.fn();
		}

		injectWooPayConnectIframe() {}

		async sendMessageAndListenWith( messageObj, listenerCallback ) {
			return new Promise( ( resolve ) => {
				this.listeners[ listenerCallback ] = resolve;
				// Simulate the postMessage response asynchronously.
				this._pendingResolve = { listenerCallback, resolve };
			} );
		}

		callbackFn() {}
	};
} );

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn().mockReturnValue( 'https://pay.woo.com' ),
} ) );

describe( 'WooPayUserConnect', () => {
	let userConnect;

	beforeEach( () => {
		userConnect = new WooPayUserConnect();
	} );

	test( 'has getPreferredPaymentMethodCallback listener', () => {
		expect(
			userConnect.listeners.getPreferredPaymentMethodCallback
		).toBeDefined();
		expect(
			typeof userConnect.listeners.getPreferredPaymentMethodCallback
		).toBe( 'function' );
	} );

	test( 'callbackFn routes get_preferred_payment_method_success to listener', () => {
		const mockCallback = jest.fn();
		userConnect.listeners.getPreferredPaymentMethodCallback = mockCallback;

		const cardData = { brand: 'visa', last4: '4242' };
		userConnect.callbackFn( {
			action: 'get_preferred_payment_method_success',
			value: cardData,
		} );

		expect( mockCallback ).toHaveBeenCalledWith( cardData );
	} );

	test( 'callbackFn routes null value for users without saved cards', () => {
		const mockCallback = jest.fn();
		userConnect.listeners.getPreferredPaymentMethodCallback = mockCallback;

		userConnect.callbackFn( {
			action: 'get_preferred_payment_method_success',
			value: null,
		} );

		expect( mockCallback ).toHaveBeenCalledWith( null );
	} );
} );
