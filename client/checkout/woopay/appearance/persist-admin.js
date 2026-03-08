/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { getAppearance, getFontRulesFromPage } from 'wcpay/checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import { isPreviewing } from 'wcpay/checkout/preview';
import { appendObjectToFormData } from './form-data';

let attempted = false;
let listeningForSaved = false;

/**
 * Extracts the current appearance from the DOM and POSTs it to the admin
 * endpoint. Returns early if preconditions aren't met.
 */
const persistAppearanceFromDOM = () => {
	const nonce = getConfig( 'adminAppearanceNonce' );
	if ( ! nonce || ! isPreviewing() ) {
		return;
	}

	const appearanceType = getAppearanceType();
	const appearance = getAppearance( appearanceType, true );
	if ( ! appearance ) {
		return;
	}

	const ajaxUrl = getConfig( 'ajaxUrl' );
	if ( ! ajaxUrl ) {
		return;
	}

	const fontRules = getFontRulesFromPage();

	const body = new FormData();
	body.append( 'action', 'wcpay_admin_set_woopay_appearance' );
	body.append( '_ajax_nonce', nonce );
	appendObjectToFormData( body, appearance );
	body.append( 'font_rules', JSON.stringify( fontRules ) );

	// Fire-and-forget — admin write always overwrites.
	fetch( ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} ).catch( () => {} );
};

/**
 * When running inside the Customizer preview, computes the live appearance
 * from the DOM and POSTs it to the admin endpoint. Runs once per page load,
 * then re-runs after each Customizer "Publish" so the cache is repopulated
 * with the updated styles.
 */
export const maybePersistAdminAppearance = () => {
	if ( attempted ) {
		return;
	}

	attempted = true;

	persistAppearanceFromDOM();

	// After a Customizer publish, the server-side customize_save_after hook
	// invalidates the cached appearance. Listen for the "saved" event so we
	// can re-extract and re-persist with the now-current styles.
	if ( ! listeningForSaved && window.wp?.customize ) {
		listeningForSaved = true;
		window.wp.customize.bind( 'saved', () => {
			persistAppearanceFromDOM();
		} );
	}
};
