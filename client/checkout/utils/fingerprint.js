/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FingerprintJS from '@fingerprintjs/fingerprintjs';

export const FINGERPRINT_GENERIC_ERROR = __(
	'An error was encountered when preparing the payment form. Please try again later.',
	'woocommerce-payments'
);

export const getFingerprint = async () => {
	const agent = await FingerprintJS.load( { monitoring: false } );

	if ( ! agent ) {
		throw new Error( FINGERPRINT_GENERIC_ERROR );
	}

	return await agent.get();
};

/**
 * Appends a hidden input with the user fingerprint to the checkout form.
 *
 * @param {Object} form        The jQuery Checkout form object.
 * @param {string} fingerprint User fingerprint.
 */
export const appendFingerprintInputToForm = ( form, fingerprint = '' ) => {
	// Remove any existing wcpay-fingerprint input.
	form.find( 'input[name="wcpay-fingerprint"]' ).remove();

	// Append an input with the correct fingerprint to the form.
	const fingerprintInput = `<input type="hidden" name="wcpay-fingerprint" value="${ fingerprint }" />`;
	form.append( fingerprintInput );
};
