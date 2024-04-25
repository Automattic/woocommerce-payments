/**
 * Internal dependencies
 */
import { WooPayConnectIframe } from 'wcpay/checkout/woopay/connect/woopay-connect-iframe';
import ReactDOM from 'react-dom';
import { getConfig } from 'wcpay/utils/checkout';
import {
	INJECTED_STATE,
	getConnectIframeInjectedState,
	setConnectIframeInjectedState,
} from 'wcpay/checkout/woopay/connect/connect-utils';

class WoopayConnect {
	iframePostMessage = null;
	listeners = {};

	constructor() {
		// The initial state of these listeners serve as a placeholder.
		this.listeners = {
			getIframePostMessageCallback: () => {},
		};
		this.removeMessageListener = this.attachMessageListener();
		this.injectWooPayConnectIframe();
	}

	/**
	 * Attaches a message listener to the window. The listener will call the
	 * callbackFn with the event data as an argument. This is how the Connects
	 * communicate with the WooPayConnectIframe.
	 *
	 * @return {(function(): void)|*} A function that detaches the message listener.
	 */
	attachMessageListener() {
		const messageListener = ( event ) => {
			const isFromWoopayHost = getConfig( 'woopayHost' ).startsWith(
				event.origin
			);

			// If the message is not from WooPay, ignore it.
			if ( ! isFromWoopayHost ) {
				return;
			}

			this.callbackFn( event.data );
		};

		window.addEventListener( 'message', messageListener );

		return () => {
			window.removeEventListener( 'message', messageListener );
		};
	}

	/**
	 * Detaches the message listener from the window.
	 */
	detachMessageListener() {
		if ( typeof this.removeMessageListener === 'function' ) {
			this.removeMessageListener();
		}
	}

	/**
	 * Injects the WooPayConnectIframe into the page.
	 */
	injectWooPayConnectIframe() {
		const injectedState = getConnectIframeInjectedState();

		if ( injectedState === INJECTED_STATE.INJECTED ) {
			// iFrame is already injected, get the postMessage function directly.
			const iframe = document.querySelector( '#woopay-connect-iframe' );
			if ( iframe ) {
				this.iframePostMessage = Promise.resolve( ( value ) => {
					iframe.contentWindow.postMessage(
						value,
						getConfig( 'woopayHost' )
					);
				} );
			}

			return;
		} else if ( injectedState === INJECTED_STATE.INJECTING ) {
			// iFrame is being injected, expect the postMessage to be set by an event callback when iFrame loads.
			this.iframePostMessage = new Promise( ( resolve ) => {
				this.listeners.getIframePostMessageCallback = resolve;
			} );

			return;
		}

		// iFrame is not injected, let's inject it.
		setConnectIframeInjectedState( INJECTED_STATE.INJECTING );

		const hiddenDiv = document.createElement( 'div' );
		hiddenDiv.style.visibility = 'hidden';
		hiddenDiv.style.position = 'fixed';
		hiddenDiv.style.height = '0';
		hiddenDiv.style.width = '0';
		hiddenDiv.style.bottom = '0';
		hiddenDiv.style.right = '0';
		hiddenDiv.id = 'woopay-connect-iframe-container';

		document.body.appendChild( hiddenDiv );

		// self is used to maintain this context in the promise.
		const self = this;
		this.iframePostMessage = new Promise( ( resolve ) => {
			self.listeners.getIframePostMessageCallback = resolve;
		} );

		ReactDOM.render( <WooPayConnectIframe />, hiddenDiv );
	}

	/**
	 * Injects the WooPayConnectIframe into the page and returns a promise that
	 * resolves to a function that can be used to send postMessage events to the
	 * iframe.
	 *
	 * @return {Object} An object that contains a way to communicate with the iframe and a way to remove the iframe.
	 */
	injectTemporaryWooPayConnectIframe() {
		let resolvePostMessage;
		const resolvePostMessagePromise = new Promise( ( resolve ) => {
			resolvePostMessage = resolve;
		} );

		const iframe = document.createElement( 'iframe' );
		iframe.id = 'temp-woopay-connect-iframe';
		iframe.src = getConfig( 'woopayHost' ) + '/connect/';
		iframe.height = 0;
		iframe.width = 0;
		iframe.border = 'none';
		iframe.margin = 0;
		iframe.padding = 0;
		iframe.overflow = 'hidden';
		iframe.display = 'block';
		iframe.visibility = 'hidden';
		iframe.position = 'fixed';
		iframe.pointerEvents = 'none';
		iframe.userSelect = 'none';
		iframe.addEventListener( 'load', () => {
			resolvePostMessage( ( message ) =>
				iframe.contentWindow.postMessage(
					message,
					getConfig( 'woopayHost' )
				)
			);
		} );

		document.body.appendChild( iframe );

		// Provides a handle to remove the iframe.
		const removeTemporaryIframe = () => {
			document.body.removeChild( iframe );
		};

		return { resolvePostMessagePromise, removeTemporaryIframe };
	}

	/**
	 * Sends a message to the WooPayConnectIframe and configures the listener.
	 *
	 * @param {Object} messageObj The message to send to the WooPayConnectIframe.
	 * @param {string} listenerCallback The name of the listener callback to use to resolve the promise.
	 * @return {Promise<*>} Resolves to the response from the WooPayConnectIframe.
	 */
	async sendMessageAndListenWith( messageObj, listenerCallback ) {
		const promise = new Promise( ( resolve ) => {
			this.listeners[ listenerCallback ] = resolve;
		} );

		const postMessage = await this.iframePostMessage;
		postMessage( messageObj );

		return await promise;
	}

	/**
	 * The callback function that is called when a message is received from the WooPayConnectIframe.
	 *
	 * @param {Object} data The data received from the WooPayConnectIframe.
	 */
	callbackFn( data ) {
		switch ( data.action ) {
			case 'get_iframe_post_message_success':
				this.listeners.getIframePostMessageCallback( data.value );
				break;
		}
	}
}

export default WoopayConnect;
