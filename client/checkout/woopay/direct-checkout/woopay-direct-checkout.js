/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import UserConnect from 'wcpay/checkout/woopay/connect/user-connect';
import SessionConnect from 'wcpay/checkout/woopay/connect/session-connect';

/**
 * The WooPayDirectCheckout class is responsible for injecting the WooPayConnectIframe into the
 * page and for handling the communication between the WooPayConnectIframe and the page.
 */
class WooPayDirectCheckout {
	static userConnect;
	static sessionConnect;
	static encryptedSessionDataPromise;
	static redirectElements = {
		CLASSIC_CART_PROCEED_BUTTON: '.wc-proceed-to-checkout',
		BLOCKS_CART_PROCEED_BUTTON:
			'.wp-block-woocommerce-proceed-to-checkout-block',
	};

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
	 * Teardown WooPayDirectCheckout.
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
	static isWooPayDirectCheckoutEnabled() {
		return getConfig( 'isWooPayDirectCheckoutEnabled' );
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
		// We're intentionally adding a try-catch block to catch any errors
		// that might occur other than the known validation errors.
		try {
			let encryptedSessionData;
			if ( this.isEncryptedSessionDataPrefetched() ) {
				encryptedSessionData = await this.encryptedSessionDataPromise;
			} else {
				encryptedSessionData = await this.getEncryptedSessionData();
			}
			if ( ! this.isValidEncryptedSessionData( encryptedSessionData ) ) {
				throw new Error(
					'Could not retrieve encrypted session data from store.'
				);
			}

			const woopaySessionData = await this.getSessionConnect().sendRedirectSessionDataToWooPay(
				encryptedSessionData
			);
			if ( ! woopaySessionData?.redirect_url ) {
				throw new Error( 'Could not retrieve WooPay checkout URL.' );
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

		// For every new element added here, ensure the selector is added to the redirectElements object.
		addElementBySelector(
			this.redirectElements.CLASSIC_CART_PROCEED_BUTTON
		);
		addElementBySelector(
			this.redirectElements.BLOCKS_CART_PROCEED_BUTTON
		);

		return elements;
	}

	/**
	 * Gets the classic 'Proceed to Checkout' button.
	 *
	 * @return {Element} The classic 'Proceed to Checkout' button.
	 */
	static getClassicProceedToCheckoutButton() {
		return document.querySelector(
			this.redirectElements.CLASSIC_CART_PROCEED_BUTTON
		);
	}

	/**
	 * Adds a click-event listener to the given elements that redirects to the WooPay checkout page.
	 *
	 * @param {*[]} elements The elements to add a click-event listener to.
	 * @param {boolean} useCheckoutRedirect Whether to use the `checkout_redirect` flag to let WooPay handle the checkout flow.
	 */
	static redirectToWooPay( elements, useCheckoutRedirect = false ) {
		elements.forEach( ( element ) => {
			element.addEventListener( 'click', async ( event ) => {
				// Store href before the async call to not lose the reference.
				const currTargetHref = event.currentTarget.querySelector( 'a' )
					?.href;

				// If there's no link where to redirect the user, do not break the expected behavior.
				if ( ! currTargetHref ) {
					this.teardown();
					return;
				}

				event.preventDefault();

				try {
					let woopayRedirectUrl = await this.resolveWooPayRedirectUrl();
					if ( useCheckoutRedirect ) {
						woopayRedirectUrl += '&checkout_redirect=1';
					}

					this.teardown();
					// TODO: Add telemetry as to _how long_ it took to get to this step.
					window.location.href = woopayRedirectUrl;
				} catch ( error ) {
					// TODO: Add telemetry as to _why_ we've short-circuited the WooPay checkout flow.
					console.warn( error ); // eslint-disable-line no-console

					this.teardown();
					window.location.href = currTargetHref;
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

	/**
	 * Prefetches the encrypted session data if not on the product page.
	 */
	static maybePrefetchEncryptedSessionData() {
		const isProductPage =
			window?.wcpayWooPayDirectCheckout?.params?.is_product_page;
		if ( typeof isProductPage === 'undefined' || isProductPage ) {
			return;
		}

		this.encryptedSessionDataPromise = new Promise( ( resolve ) => {
			resolve( this.getEncryptedSessionData() );
		} );
	}

	/**
	 * Sets the encrypted session data as not prefetched.
	 */
	static setEncryptedSessionDataAsNotPrefetched() {
		this.encryptedSessionDataPromise = null;
	}

	/**
	 * Checks if the encrypted session data has been prefetched.
	 *
	 * @return {boolean} True if the encrypted session data has been prefetched.
	 */
	static isEncryptedSessionDataPrefetched() {
		return typeof this.encryptedSessionDataPromise?.then === 'function';
	}
}

export default WooPayDirectCheckout;
