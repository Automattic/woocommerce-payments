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
	 * Resolves the redirect URL to the WooPay checkout page or throws an error if the request fails.
	 *
	 * @return {string} The redirect URL.
	 * @throws {Error} If the session data could not be sent to WooPay.
	 */
	static async resolveWooPayRedirectUrl() {
		try {
			const encryptedSessionData = await this.getEncryptedSessionData();
			if ( ! this.isValidEncryptedSessionData( encryptedSessionData ) ) {
				throw new Error(
					'Could not retrieve encrypted session from store.'
				);
			}

			const woopaySessionData = await this.getSessionConnect().sendRedirectSessionDataToWooPay(
				encryptedSessionData
			);
			if ( ! woopaySessionData?.redirect_url ) {
				throw new Error( 'Invalid WooPay session.' );
			}

			return woopaySessionData.redirect_url;
		} catch ( error ) {
			throw new Error( error.message );
		}
	}

	/**
	 * Checks if the encrypted session object is valid.
	 *
	 * @param {Object} encryptedSessionData The encrypted session data.
	 * @return {boolean} True if the session is valid.
	 */
	static isValidEncryptedSessionData( encryptedSessionData ) {
		return (
			encryptedSessionData &&
			encryptedSessionData?.blog_id &&
			encryptedSessionData?.data?.session &&
			encryptedSessionData?.data?.iv &&
			encryptedSessionData?.data?.hash
		);
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
	 * Adds a click-event listener to the given elements that redirects to the WooPay checkout page.
	 *
	 * @param {*[]} elements The elements to add a click-event listener to.
	 * @param {boolean} useCheckoutRedirect Whether to use the `woopay_checkout_redirect` flag to let WooPay handle the checkout flow.
	 */
	static redirectToWooPay( elements, useCheckoutRedirect ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				event.preventDefault();

				try {
					let woopayRedirectUrl = await this.resolveWooPayRedirectUrl();
					if ( useCheckoutRedirect ) {
						woopayRedirectUrl += '&woopay_checkout_redirect=1';
					}

					this.teardown();
					window.location.href = woopayRedirectUrl;
				} catch ( error ) {
					// TODO: Add telemetry for this flow.
					console.error( error );

					this.teardown();
					window.location.href = event.target.href;
				}
			} );
		} );
	}

	/**
	 * Gets the WooPay session.
	 *
	 * @return {Promise<Promise<*>|*>} Resolves to the WooPay session response.
	 */
	static async getEncryptedSessionData() {
		return request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
			{
				_ajax_nonce: getConfig( 'woopaySessionNonce' ),
			}
		);
	}
}

export default WoopayDirectCheckout;
