/**
 * WooPay shopper write path — conditional first-write on checkout.
 *
 * On shortcode checkout page loads, extracts the DOM appearance and
 * POSTs it to the shopper AJAX endpoint if no valid appearance is
 * stored for the current cache version.
 */

/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { buildAjaxURL } from 'wcpay/utils/express-checkout';
import { getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { appendAppearanceToFormData } from './form-data';

let persistAttempted = false;

/**
 * Fire-and-forget POST of the appearance to the shopper conditional write
 * endpoint. The server only accepts the write if no valid appearance exists
 * for the current styles cache version.
 *
 * @param {Object} appearance The computed appearance object.
 */
export const maybePersistWoopayAppearance = ( appearance ) => {
	if ( persistAttempted || ! appearance ) {
		return;
	}

	persistAttempted = true;

	const wcAjaxUrl = getConfig( 'wcAjaxUrl' );
	if ( ! wcAjaxUrl ) {
		return;
	}

	const url = buildAjaxURL( wcAjaxUrl, 'shopper_set_woopay_appearance' );

	const fontRules = getFontRulesFromPage();

	const body = new FormData();
	body.append( '_ajax_nonce', getConfig( 'woopaySessionNonce' ) );
	appendAppearanceToFormData( body, appearance );
	body.append( 'font_rules', JSON.stringify( fontRules ) );

	// Fire-and-forget — we don't need the response.
	fetch( url, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} ).catch( () => {} );
};
