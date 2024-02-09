/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import UserConnect from 'wcpay/checkout/woopay/connect/user-connect';
import SessionConnect from 'wcpay/checkout/woopay/connect/session-connect';

/**
 * The WoopayDirectCheckout class is responsible for injecting the WooPayConnectIframe into the
 * page and for handling the communication between the WooPayConnectIframe and the page.
 */
class WoopayDirectCheckout {
	static userConnect;
	static sessionConnect;

	/**
	 * Initializes the WooPay direct checkout feature.
	 */
	static init() {
		this.getSessionConnect();
	}

	/**
	 * Gets the user connect instance.
	 *
	 * @return {*} The instance of a WooPay user connect.
	 */
	static getUserConnect() {
		if ( ! this.userConnect ) {
			this.userConnect = new UserConnect();
		}

		return this.userConnect;
	}

	/**
	 * Gets the session connect.
	 *
	 * @return {*} The instance of a WooPay session connect.
	 */
	static getSessionConnect() {
		if ( ! this.sessionConnect ) {
			this.sessionConnect = new SessionConnect();
		}

		return this.sessionConnect;
	}

	/**
	 * Teardown WoopayDirectCheckout.
	 */
	static teardown() {
		this.sessionConnect?.detachMessageListener();
		this.userConnect?.detachMessageListener();

		this.sessionConnect = null;
		this.userConnect = null;
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
	 * Checks if the user is logged in.
	 *
	 * @return {Promise<*>} Resolves to true if the user is logged in.
	 */
	static async isUserLoggedIn() {
		return this.getUserConnect().isUserLoggedIn();
	}

	/**
	 * Checks if third-party cookies are enabled.
	 *
	 * @return {Promise<*>} Resolves to true if third-party cookies are enabled.
	 */
	static async isWooPayThirdPartyCookiesEnabled() {
		return this.getSessionConnect().isWooPayThirdPartyCookiesEnabled();
	}

	/**
	 * Sends the session data to the WooPayConnectIframe.
	 *
	 * @return {Promise<*>} Resolves to the redirect URL.
	 */
	static async sendRedirectSessionDataToWooPay() {
		const woopaySession = await this.getWooPaySessionFromMerchant();
		const woopaySessionData = await this.getSessionConnect().sendRedirectSessionDataToWooPay(
			woopaySession
		);

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

				const woopayRedirectUrl = await this.sendRedirectSessionDataToWooPay();
				this.teardown();

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

				const woopayRedirectUrl = await this.sendRedirectSessionDataToWooPay();
				this.teardown();

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
