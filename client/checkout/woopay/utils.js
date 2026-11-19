/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { isEmail } from 'wcpay/utils/email-validation';

// Waits for the element to exist as in the Blocks checkout, sometimes the field is not immediately available.
export const getTargetElement = ( selector ) => {
	if ( ! selector ) {
		return null;
	}
	return new Promise( ( resolve ) => {
		if ( document.querySelector( selector ) ) {
			return resolve( document.querySelector( selector ) );
		}

		const checkoutBlock = document.querySelector(
			'[data-block-name="woocommerce/checkout"]'
		);

		if ( ! checkoutBlock ) {
			return resolve( null );
		}

		const observer = new MutationObserver( ( mutationList, obs ) => {
			if ( document.querySelector( selector ) ) {
				resolve( document.querySelector( selector ) );
				obs.disconnect();
			}
		} );

		observer.observe( checkoutBlock, {
			childList: true,
			subtree: true,
		} );
	} );
};

export const validateEmail = ( value ) => {
	if ( ! isEmail( value ) ) {
		return false;
	}

	// Keep the two-character TLD check before skipping WooPay email entry.
	const address = value.endsWith( '.' ) ? value.slice( 0, -1 ) : value;
	const topLevelDomain = address.slice( address.lastIndexOf( '.' ) + 1 );
	return /^[a-z][a-z\d-]*[a-z\d]$/i.test( topLevelDomain );
};

export const appendRedirectionParams = ( woopayUrl ) => {
	const isPayForOrder = window.wcpayConfig.pay_for_order;
	const orderId = window.wcpayConfig.order_id;
	const key = window.wcpayConfig.key;
	const billingEmail = window.wcpayConfig.billing_email;

	if ( ! isPayForOrder || ! orderId || ! key ) {
		return woopayUrl;
	}

	const url = new URL( woopayUrl );
	url.searchParams.append( 'pay_for_order', isPayForOrder );
	url.searchParams.append( 'order_id', orderId );
	url.searchParams.append( 'key', key );
	url.searchParams.append( 'billing_email', billingEmail );

	return url.href;
};

/**
 * Checks if a session cookie is set in order to determine if the user has opted to skip WooPay.
 *
 * @return {boolean} True if the user has opted to skip WooPay.
 */
export const shouldSkipWooPay = () => {
	const cookies = document.cookie.split( ';' );
	const skipWooPayCookie = cookies.find( ( cookie ) =>
		cookie.includes( 'skip_woopay' )
	);

	if ( ! skipWooPayCookie ) {
		return false;
	}

	const skipWooPayCookieSplit = skipWooPayCookie?.split( '=' );

	return (
		skipWooPayCookieSplit[ 0 ].trim() === 'skip_woopay' &&
		skipWooPayCookieSplit[ 1 ].trim() === '1'
	);
};

/**
 * Deletes the skip_woopay cookie.
 * This should be called when the user explicitly opts to pay with WooPay.
 */
export const deleteSkipWooPayCookie = () => {
	if ( ! shouldSkipWooPay() ) {
		return;
	}

	document.cookie =
		'skip_woopay=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;';
};

/**
 * Returns true when the current page is a WooCommerce shortcode checkout
 * (i.e. the checkout page using the [woocommerce_checkout] shortcode, not
 * the Blocks-based checkout).
 *
 * Uses a server-side flag (`is_checkout() && ! has_block('woocommerce/checkout')`)
 * exposed via wcpayConfig, which is more robust than DOM class detection
 * (e.g. `.woocommerce-billing-fields` can be removed by checkout field
 * editor plugins or custom code).
 *
 * @return {boolean} True on shortcode checkout pages.
 */
export const isShortcodeCheckout = () => !! getConfig( 'isShortcodeCheckout' );

/**
 * Determine Global theming availability for the entrypoint based on the appearanceType.
 *
 * @param {string} appearanceType entrypoint identifier.
 * @return {boolean} True if Global theming should be enabled for the entrypoint.
 */
export const isSupportedThemeEntrypoint = ( appearanceType ) => {
	return [
		'woopay_shortcode_checkout',
		'woopay_blocks_checkout',
		'blocks_checkout',
		'bnpl_product_page',
		'bnpl_classic_cart',
		'bnpl_cart_block',
	].includes( appearanceType );
};
