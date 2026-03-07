/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import { getAppearance } from 'wcpay/checkout/upe-styles';
import { getAppearanceType } from 'wcpay/checkout/utils';
import { isPreviewing } from 'wcpay/checkout/preview';
import { appendObjectToFormData } from './form-data';

let attempted = false;

/**
 * When running inside the Customizer preview, computes the live appearance
 * from the DOM and POSTs it to the admin endpoint. Runs once per page load.
 */
export const maybePersistAdminAppearance = () => {
	if ( attempted ) {
		return;
	}

	attempted = true;

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

	const body = new FormData();
	body.append( 'action', 'wcpay_admin_set_woopay_appearance' );
	body.append( '_ajax_nonce', nonce );
	appendObjectToFormData( body, appearance );

	// Fire-and-forget — admin write always overwrites.
	fetch( ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} ).catch( () => {} );
};
