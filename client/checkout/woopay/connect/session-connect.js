/**
 * Internal dependencies
 */
import WoopayConnect from 'wcpay/checkout/woopay/connect/woopay-connect';

class WooPaySessionConnect extends WoopayConnect {
	constructor() {
		super();

		// The initial state of these listeners serve as a placeholder.
		this.listeners = {
			...this.listeners,
			setRedirectSessionDataCallback: () => {},
			setTempThirdPartyCookieCallback: () => {},
			getIsThirdPartyCookiesEnabledCallback: () => {},
			setPreemptiveSessionDataCallback: () => {},
		};
	}

	/**
	 * Checks if third-party cookies are enabled.
	 * Note: A temporary iframe is injected to check if third-party cookies are enabled.
	 *       We avoid using WooPayConnectIframe because to test for third-party cookies, a page
	 *       refresh is required. Otherwise, if another Connect were making a request and the
	 *       iframe was refreshed, it would unintentionally abort that other Connect's request.
	 *
	 * @return {Promise<*>} Resolves to true if third-party cookies are enabled.
	 */
	async isWooPayThirdPartyCookiesEnabled() {
		const {
			resolvePostMessagePromise,
			removeTemporaryIframe,
		} = this.injectTemporaryWooPayConnectIframe();

		const isThirdPartyCookieSetPromise = new Promise( ( resolve ) => {
			this.listeners.setTempThirdPartyCookieCallback = resolve;
		} );

		// This request causes a page reload after the cookie has been set.
		const tempPostMessage = await resolvePostMessagePromise;
		tempPostMessage( { action: 'setTempThirdPartyCookie' } );

		if ( ! ( await isThirdPartyCookieSetPromise ) ) {
			return false;
		}

		const isThirdPartyCookieEnabledPromise = new Promise( ( resolve ) => {
			this.listeners.getIsThirdPartyCookiesEnabledCallback = resolve;
		} );
		tempPostMessage( { action: 'getIsThirdPartyCookiesEnabled' } );
		const isThirdPartyCookieEnabled = await isThirdPartyCookieEnabledPromise;

		// Once we have the result, we remove the temporary iframe.
		removeTemporaryIframe();

		return isThirdPartyCookieEnabled;
	}

	/**
	 * Sends session data to WooPay.
	 *
	 * @param {Object} data The data to send to WooPay.
	 * @return {Promise<*>} Resolves to the WooPay session response.
	 */
	async sendRedirectSessionDataToWooPay( data ) {
		return await super.sendMessageAndListenWith(
			{ action: 'setRedirectSessionData', value: data },
			'setRedirectSessionDataCallback'
		);
	}

	/**
	 * Sends session data to WooPay, preemptively.
	 *
	 * @param {Object} data The data to send to WooPay.
	 * @return {Promise<*>} Resolves to the WooPay session response.
	 */
	async setPreemptiveSessionData( data ) {
		return await super.sendMessageAndListenWith(
			{ action: 'setPreemptiveSessionData', value: data },
			'setPreemptiveSessionDataCallback'
		);
	}

	/**
	 * The callback function that is called when a message is received from the WooPayConnectIframe.
	 *
	 * @param {Object} data The data received from the WooPayConnectIframe.
	 */
	callbackFn( data ) {
		super.callbackFn( data );

		switch ( data.action ) {
			case 'set_redirect_session_data_success':
				this.listeners.setRedirectSessionDataCallback( data.value );
				break;
			case 'set_redirect_session_data_error':
				this.listeners.setRedirectSessionDataCallback( {
					is_error: true,
				} );
				break;
			case 'set_temp_third_party_cookie_success':
				this.listeners.setTempThirdPartyCookieCallback( data.value );
				break;
			case 'get_is_third_party_cookies_enabled_success':
				this.listeners.getIsThirdPartyCookiesEnabledCallback(
					data.value
				);
				break;
			case 'set_preemptive_session_data_success':
				this.listeners.setPreemptiveSessionDataCallback( data.value );
				break;
			case 'set_preemptive_session_data_error':
				this.listeners.setPreemptiveSessionDataCallback( {
					is_error: true,
				} );
				break;
		}
	}
}

export default WooPaySessionConnect;
