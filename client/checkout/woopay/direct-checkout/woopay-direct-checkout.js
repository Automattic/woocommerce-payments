/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import ReactDOM from 'react-dom';
import { WooPayConnectIframe } from 'wcpay/checkout/woopay/direct-checkout/woopay-connect-iframe';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';

class WoopayDirectCheckout {
	static state = {
		iframePostMessage: null,
	};
	static listeners = {
		setIframePostMessage: () => {},
		setTempThirdPartyCookie: () => {},
		setIsWooPayThirdPartyCookiesEnabled: () => {},
		setIsUserLoggedIn: () => {},
		setWoopaySessionResponse: () => {},
	};
	static actionCallback = {
		set_temp_third_party_cookie_success: 'setTempThirdPartyCookie',
		get_is_third_party_cookies_enabled_success:
			'setIsWooPayThirdPartyCookiesEnabled',
		get_is_user_logged_in_success: 'setIsUserLoggedIn',
		set_redirect_session_data_success: 'setWoopaySessionResponse',
	};

	static init() {
		this.state.iframePostMessage = new Promise( ( resolve ) => {
			this.listeners.setIframePostMessage = resolve;
		} );

		this.injectWooPayConnectIframe();
	}

	static isWooPayEnabled() {
		return getConfig( 'isWooPayEnabled' );
	}

	static injectWooPayConnectIframe() {
		const checkoutButton = document.querySelector(
			'.wc-proceed-to-checkout .checkout-button'
		);
		if ( checkoutButton ) {
			checkoutButton.parentElement.style.position = 'relative';
			const hiddenDiv = document.createElement( 'div' );
			hiddenDiv.style.visibility = 'hidden';
			hiddenDiv.style.position = 'absolute';
			hiddenDiv.style.height = '0';

			checkoutButton.parentElement.appendChild( hiddenDiv );

			ReactDOM.render(
				<WooPayConnectIframe
					listeners={ this.listeners }
					actionCallback={ this.actionCallback }
				/>,
				hiddenDiv
			);
		}
	}

	static async setTempThirdPartyCookie() {
		const setTempThirdPartyCookie = new Promise( ( resolve ) => {
			this.listeners.setTempThirdPartyCookie = resolve;
		} );

		const postMessage = await this.state.iframePostMessage;
		postMessage( { action: 'setTempThirdPartyCookie' } );

		// Since iFrame is refreshed, we need to re-init the iframePostMessage promise.
		this.state.iframePostMessage = new Promise( ( resolve ) => {
			this.listeners.setIframePostMessage = resolve;
		} );

		return await setTempThirdPartyCookie;
	}

	static async isWooPayThirdPartyCookiesEnabled() {
		const isCookieSet = await this.setTempThirdPartyCookie();
		if ( ! isCookieSet ) {
			return false;
		}

		const isWooPayThirdPartyCookiesEnabled = new Promise( ( resolve ) => {
			this.listeners.setIsWooPayThirdPartyCookiesEnabled = resolve;
		} );

		const postMessage = await this.state.iframePostMessage;
		postMessage( { action: 'getIsThirdPartyCookiesEnabled' } );

		return await isWooPayThirdPartyCookiesEnabled;
	}

	static async isUserLoggedIn() {
		const isUserLoggedIn = new Promise( ( resolve ) => {
			this.listeners.setIsUserLoggedIn = resolve;
		} );

		const postMessage = await this.state.iframePostMessage;
		postMessage( { action: 'getIsUserLoggedIn' } );

		return await isUserLoggedIn;
	}

	static async sendSessionDataToWooPay() {
		const woopaySessionData = new Promise( ( resolve ) => {
			this.listeners.setWoopaySessionResponse = resolve;
		} );

		const woopaySession = await this.getWooPaySessionFromMerchant();
		const postMessage = await this.state.iframePostMessage;
		postMessage( {
			action: 'setRedirectSessionData',
			value: woopaySession,
		} );

		const { redirect_url: redirectUrl } = await woopaySessionData;

		return redirectUrl;
	}

	static getCheckoutRedirectElements() {
		// TODO: Add other checkout elements, if needed.
		const checkoutButton = document.querySelector(
			'.wc-proceed-to-checkout .checkout-button'
		);

		return [ checkoutButton ];
	}

	static redirectToWooPaySession( elements ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				event.preventDefault();

				const woopayRedirectUrl = await this.sendSessionDataToWooPay();

				window.location.href = woopayRedirectUrl;
			} );
		} );
	}

	static redirectToWooPay( elements ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				event.preventDefault();

				const woopayRedirectUrl = await this.sendSessionDataToWooPay();

				window.location.href = woopayRedirectUrl + '&redirect=1';
			} );
		} );
	}

	static async getWooPaySessionFromMerchant() {
		return request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
			{
				_ajax_nonce: getConfig( 'woopaySessionNonce' ),
			}
		);
	}
}

export default WoopayDirectCheckout;
