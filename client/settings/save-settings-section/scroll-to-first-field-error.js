/** @format */

/**
 * Map a settings field key (e.g. `account_business_support_phone`) to the DOM id
 * of its input. Falls back to `<setting-key-with-hyphens>-input` so that any field
 * following the convention is supported without an explicit entry here.
 *
 * @param {string} settingKey Settings field key from the REST error details.
 * @return {string} DOM element id to look up.
 */
const fieldKeyToInputId = ( settingKey ) => {
	const explicit = {
		account_business_support_phone: 'account-business-support-phone-input',
		account_business_support_email: 'account-business-support-email-input',
		account_statement_descriptor: 'account-statement-descriptor-input',
	};

	return (
		explicit[ settingKey ] || `${ settingKey.replace( /_/g, '-' ) }-input`
	);
};

/**
 * Scroll the page so the first field referenced in a server-error `details`
 * object is brought into view, mirroring the way native form validation
 * focuses the offending input. No-ops when no matching DOM element exists.
 *
 * @param {Object|undefined|null} details `error.data.details` from a saving error.
 */
const scrollToFirstFieldError = ( details ) => {
	if ( ! details || typeof details !== 'object' ) {
		return;
	}

	const fieldKey = Object.keys( details )[ 0 ];
	if ( ! fieldKey ) {
		return;
	}

	const element = document.getElementById( fieldKeyToInputId( fieldKey ) );
	if ( ! element || typeof element.scrollIntoView !== 'function' ) {
		return;
	}

	element.scrollIntoView( { behavior: 'smooth', block: 'center' } );
};

export default scrollToFirstFieldError;
