/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import { isShortcodeCheckout } from 'wcpay/checkout/woopay/utils';

// Guards against a duplicate init: the WooPay <Login> component re-renders and fires the
// redirect message twice, and we only ever want one init_woopay request in flight. This used
// to live on WCPayAPI as `isWooPayRequesting`, but it's really WooPay's own concern, so it sits
// here with the only code that touches it. It's a module-level flag rather than per-API-instance
// state, which makes it a per-page guard — fine here, since the WooPay init flows don't overlap.
let isRequesting = false;

/**
 * Fires the `init_woopay` request, resolving the checkout appearance to send along with it.
 *
 * This deliberately lives outside the WCPayAPI class. Resolving the appearance pulls in the
 * UPE style helpers, which carry tinycolor2 — and WCPayAPI is imported by lean bundles such as
 * the express checkout buttons that never touch WooPay. Keeping it here means tinycolor2 only
 * ends up in the bundles that actually call this, instead of riding along everywhere WCPayAPI does.
 *
 * Takes the request transport directly (e.g. `api.request`) rather than the whole API instance,
 * so it depends only on a way to talk to the server, not on the shape of WCPayAPI.
 *
 * @param {Function} request             The request transport (e.g. `api.request`).
 * @param {string}   [userEmail]         The shopper's email address.
 * @param {string}   [woopayUserSession] The WooPay user session token.
 * @return {Promise<{url: Location}>|undefined} The request promise, or undefined when a request is already in flight.
 */
export const initWooPay = ( request, userEmail, woopayUserSession ) => {
	if ( isRequesting ) {
		return undefined;
	}

	isRequesting = true;
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

	return request( buildAjaxURL( wcAjaxUrl, 'init_woopay' ), {
		_wpnonce: nonce,
		appearance,
		font_rules: fontRules,
		email: userEmail,
		user_session: woopayUserSession,
		order_id: getConfig( 'order_id' ),
		key: getConfig( 'key' ),
		billing_email: getConfig( 'billing_email' ),
	} ).finally( () => {
		isRequesting = false;
	} );
};
