/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import ReactDOM from 'react-dom';
import { WooPayConnectIframe } from 'wcpay/checkout/woopay/direct-checkout/woopay-connect-iframe';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';

/**
 * The WoopayDirectCheckout class is responsible for injecting the WooPayConnectIframe into the
 * page and for handling the communication between the WooPayConnectIframe and the page.
 */
class WoopayDirectCheckout {
	static state = {
		// The iframePostMessage promise is resolved when the WooPayConnectIframe is loaded. The
		// promise resolves to a function that can be used to send messages to the WooPayConnectIframe.
		iframePostMessage: null,
	};
	/**
	 * The listeners object contains functions that are called when the WooPayConnectIframe
	 * responds to an action. The listeners are "resolve" functions of Promises.
	 */
	static listeners = {
		setIframePostMessage: () => {},
		setTempThirdPartyCookie: () => {},
		setIsWooPayThirdPartyCookiesEnabled: () => {},
		setIsUserLoggedIn: () => {},
		setWoopaySessionResponse: () => {},
	};
	/**
	 * The actionCallback object maps iframe actions (keys) to listeners (values). Take
	 * "get_is_user_logged_in_success" for example: we send the action "get_is_user_logged_in_success"
	 * to the WooPayConnectIframe and, when the iframe responds, it uses the "setIsUserLoggedIn" listener.
	 *
	 * The reason for this configuration is that it allows us to extend the functionality of the
	 * WooPayConnectIframe without having to change the WooPayConnectIframe itself.
	 */
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

	/**
	 * Checks if WooPay is enabled.
	 *
	 * @return {boolean} True if WooPay is enabled.
	 */
	static isWooPayEnabled() {
		return getConfig( 'isWooPayEnabled' );
	}

	/**
	 * Injects the WooPayConnectIframe into the page.
	 */
	static injectWooPayConnectIframe() {
		const appendIframe = ( element ) => {
			element.parentElement.style.position = 'relative';
			const hiddenDiv = document.createElement( 'div' );
			hiddenDiv.style.visibility = 'hidden';
			hiddenDiv.style.position = 'absolute';
			hiddenDiv.style.height = '0';

			element.parentElement.appendChild( hiddenDiv );

			ReactDOM.render(
				<WooPayConnectIframe
					listeners={ this.listeners }
					actionCallback={ this.actionCallback }
				/>,
				hiddenDiv
			);
		};

		const checkoutButtonClassic = document.querySelector(
			'.wc-proceed-to-checkout .checkout-button'
		);
		const checkoutButtonBlocks = document.querySelector(
			'.wp-block-woocommerce-proceed-to-checkout-block'
		);
		if ( checkoutButtonClassic ) {
			appendIframe( checkoutButtonClassic );
		} else if ( checkoutButtonBlocks ) {
			appendIframe( checkoutButtonBlocks );
		}
	}

	/**
	 * Sets a temporary third party cookie in the WooPayConnectIframe.
	 *
	 * @return {Promise<*>} Resolves to true if the cookie was set successfully.
	 */
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

	/**
	 * Checks if third party cookies are enabled in the WooPayConnectIframe.
	 *
	 * @return {Promise<*>} Resolves to true if third party cookies are enabled.
	 */
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

	/**
	 * Checks if the user is logged in.
	 *
	 * @return {Promise<*>} Resolves to true if the user is logged in.
	 */
	static async isUserLoggedIn() {
		const isUserLoggedIn = new Promise( ( resolve ) => {
			this.listeners.setIsUserLoggedIn = resolve;
		} );

		const postMessage = await this.state.iframePostMessage;
		postMessage( { action: 'getIsUserLoggedIn' } );

		return await isUserLoggedIn;
	}

	/**
	 * Sends the session data to the WooPayConnectIframe.
	 *
	 * @return {Promise<*>} Resolves to the redirect URL.
	 */
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

	/**
	 * Gets the checkout redirect elements.
	 *
	 * @return {*[]} The checkout redirect elements.
	 */
	static getCheckoutRedirectElements() {
		const elements = [];
		const addElementBySelector = ( selector ) => {
			const element = document.querySelector( selector );
			if ( element ) {
				elements.push( element );
			}
		};

		// Classic 'Proceed to Checkout' button.
		addElementBySelector( '.wc-proceed-to-checkout .checkout-button' );
		// Blocks 'Proceed to Checkout' button.
		addElementBySelector(
			'.wp-block-woocommerce-proceed-to-checkout-block'
		);

		return elements;
	}

	/**
	 * Adds a click-event listener that redirects to the WooPay checkout page to the given elements.
	 *
	 * @param {*[]} elements The elements to add a click-event listener to.
	 */
	static redirectToWooPaySession( elements ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				event.preventDefault();

				const woopayRedirectUrl = await this.sendSessionDataToWooPay();

				window.location.href = woopayRedirectUrl;
			} );
		} );
	}

	/**
	 * Adds a click-event listener that redirects to WooPay and lets WooPay handle the checkout flow
	 * to the given elements.
	 *
	 * @param {*[]} elements The elements to add a click-event listener to.
	 */
	static redirectToWooPay( elements ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				event.preventDefault();

				const woopayRedirectUrl = await this.sendSessionDataToWooPay();

				window.location.href =
					woopayRedirectUrl + '&woopay_checkout_redirect=1';
			} );
		} );
	}

	/**
	 * Gets the WooPay session.
	 *
	 * @return {Promise<Promise<*>|*>} Resolves to the WooPay session response.
	 */
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
