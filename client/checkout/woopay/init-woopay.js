/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import { isShortcodeCheckout } from 'wcpay/checkout/woopay/utils';

/**
 * Fires the `init_woopay` request, resolving the checkout appearance to send along with it.
 *
 * This deliberately lives outside the WCPayAPI class. Resolving the appearance pulls in the
 * UPE style helpers, which carry tinycolor2 — and WCPayAPI is imported by lean bundles such as
 * the express checkout buttons that never touch WooPay. Keeping it here means tinycolor2 only
 * ends up in the bundles that actually call this, instead of riding along everywhere WCPayAPI does.
 *
 * Takes the API instance so it can reuse its `request` transport and the in-flight guard.
 *
 * @param {Object} api                 The WCPayAPI instance.
 * @param {string} [userEmail]         The shopper's email address.
 * @param {string} [woopayUserSession] The WooPay user session token.
 * @return {Promise|undefined} The request promise, or undefined when a request is already in flight.
 */
export const initWooPay = ( api, userEmail, woopayUserSession ) => {
	if ( api.isWooPayRequesting ) {
		return undefined;
	}

	api.isWooPayRequesting = true;
	const wcAjaxUrl = getConfig( 'wcAjaxUrl' );
	const nonce = getConfig( 'initWooPayNonce' );
	let appearance = null;
	let fontRules = null;
	if ( getConfig( 'isWooPayGlobalThemeSupportEnabled' ) ) {
		if ( isShortcodeCheckout() ) {
			const appearanceType = getAppearanceType();
			appearance = getAppearance( appearanceType, true );
			fontRules = getFontRulesFromPage();
		} else {
			appearance = getConfig( 'woopayAppearance' );
			fontRules = getConfig( 'woopayFontRules' );
		}
	}

	return api
		.request( buildAjaxURL( wcAjaxUrl, 'init_woopay' ), {
			_wpnonce: nonce,
			appearance,
			font_rules: fontRules,
			email: userEmail,
			user_session: woopayUserSession,
			order_id: getConfig( 'order_id' ),
			key: getConfig( 'key' ),
			billing_email: getConfig( 'billing_email' ),
		} )
		.finally( () => {
			api.isWooPayRequesting = false;
		} );
};
