/**
 * Internal dependencies
 */
import WoopayConnect from 'wcpay/checkout/woopay/connect/woopay-connect';
import { getPostMessageTimeout } from 'wcpay/checkout/woopay/connect/connect-utils';

class WooPaySessionConnect extends WoopayConnect {
	constructor() {
		super();

		// The initial state of these listeners serve as a placeholder.
		this.listeners = {
			...this.listeners,
			setRedirectSessionDataCallback: () => {},
			setTempThirdPartyCookieCallback: () => {},
			getIsWooPayReachableCallback: () => {},
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

		const isThirdPartyCookieSetPromise = new Promise(
			( resolve, reject ) => {
				let isRejected = false;

				// Create a fail-safe timeout in case the WooPayConnectIframe does not respond.
				const rejectTimeoutId = setTimeout( () => {
					isRejected = true;

					reject(
						new Error(
							'WooPayConnectIframe did not respond within the allotted time.'
						)
					);
				}, getPostMessageTimeout() );

				this.listeners.setTempThirdPartyCookieCallback = ( value ) => {
					if ( isRejected ) {
						return;
					}

					if ( rejectTimeoutId ) {
						clearTimeout( rejectTimeoutId );
					}

					resolve( value );
				};
			}
		);

		if ( typeof resolvePostMessagePromise?.then !== 'function' ) {
			return false;
		}

		// This request causes a page reload after the cookie has been set.
		const tempPostMessage = await resolvePostMessagePromise;
		tempPostMessage( { action: 'setTempThirdPartyCookie' } );

		try {
			if ( ! ( await isThirdPartyCookieSetPromise ) ) {
				// Once we have the result, we remove the temporary iframe.
				removeTemporaryIframe();
				return false;
			}
		} catch ( error ) {
			// Once we have the result, we remove the temporary iframe.
			removeTemporaryIframe();
			return false;
		}

		const isThirdPartyCookieEnabledPromise = new Promise(
			( resolve, reject ) => {
				let isRejected = false;

				// Create a fail-safe timeout in case the WooPayConnectIframe does not respond.
				const rejectTimeoutId = setTimeout( () => {
					isRejected = true;

					reject(
						new Error(
							'WooPayConnectIframe did not respond within the allotted time.'
						)
					);
				}, getPostMessageTimeout() );

				this.listeners.getIsThirdPartyCookiesEnabledCallback = (
					value
				) => {
					if ( isRejected ) {
						return;
					}

					if ( rejectTimeoutId ) {
						clearTimeout( rejectTimeoutId );
					}

					resolve( value );
				};
			}
		);
		tempPostMessage( { action: 'getIsThirdPartyCookiesEnabled' } );

		try {
			const isThirdPartyCookieEnabled = await isThirdPartyCookieEnabledPromise;

			return isThirdPartyCookieEnabled;
		} catch ( error ) {
			return false;
		} finally {
			// Once we have the result, we remove the temporary iframe.
			removeTemporaryIframe();
		}
	}

	/**
	 * Sends session data to WooPay.
	 *
	 * @param {Object} data The data to send to WooPay.
	 * @return {Promise<Object|null>} Resolves to the WooPay session response.
	 */
	async sendRedirectSessionDataToWooPay( data ) {
		try {
			return await super.sendMessageAndListenWith(
				{ action: 'setRedirectSessionData', value: data },
				'setRedirectSessionDataCallback'
			);
		} catch ( error ) {
			return null;
		}
	}

	/**
	 * Sends session data to WooPay, preemptively.
	 *
	 * @param {Object} data The data to send to WooPay.
	 * @return {Promise<Object|null>} Resolves to the WooPay session response.
	 */
	async setPreemptiveSessionData( data ) {
		try {
			return await super.sendMessageAndListenWith(
				{ action: 'setPreemptiveSessionData', value: data },
				'setPreemptiveSessionDataCallback'
			);
		} catch ( error ) {
			return null;
		}
	}

	/**
	 * Checks if WooPay is reachable.
	 *
	 * @return {Promise<bool>} Resolves to true if WooPay is reachable.
	 */
	async isWooPayReachable() {
		try {
			return await this.sendMessageAndListenWith(
				{ action: 'isWooPayReachable' },
				'getIsWooPayReachableCallback'
			);
		} catch ( error ) {
			return false;
		}
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
			case 'get_is_woopay_reachable_success':
				this.listeners.getIsWooPayReachableCallback( data.value );
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
