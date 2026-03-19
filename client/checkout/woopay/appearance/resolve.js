/**
 * WooPay appearance resolution — prefer server-stored, fall back to DOM.
 *
 * Used by express button, express checkout iframe, and email input iframe
 * to resolve the WooPay appearance in a consistent way:
 *   1. If global theme support is disabled, returns null.
 *   2. Prefers the server-computed appearance from wcpayConfig.
 *   3. Falls back to DOM extraction on shortcode checkout pages.
 *   4. Persists the DOM-extracted appearance for future page loads.
 */

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { getAppearance } from 'checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import {
	isSupportedThemeEntrypoint,
	isShortcodeCheckout,
} from 'wcpay/checkout/woopay/utils';
import { maybePersistWoopayAppearance } from 'wcpay/checkout/woopay/appearance/persist';

/**
 * Resolves the WooPay appearance for the current page context.
 *
 * @return {Object|null} The resolved appearance object, or null if unavailable.
 */
export function resolveWoopayAppearance() {
	const appearanceType = getAppearanceType();

	if (
		! isSupportedThemeEntrypoint( appearanceType ) ||
		! getConfig( 'isWooPayGlobalThemeSupportEnabled' )
	) {
		return null;
	}

	// Prefer server-computed appearance (available for themes with theme.json).
	const serverAppearance = getConfig( 'woopayAppearance' );
	if ( serverAppearance ) {
		return serverAppearance;
	}

	// Fall back to DOM extraction on checkout for classic themes.
	if ( isShortcodeCheckout() ) {
		const domAppearance = getAppearance( appearanceType, true );
		maybePersistWoopayAppearance( domAppearance );
		return domAppearance;
	}

	return null;
}
