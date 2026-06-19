/** @format */

/**
 * Map a settings field key (e.g. `account_business_support_phone`) to the DOM id
 * of its input. Any field whose id follows the `<setting-key-with-hyphens>-input`
 * convention is resolved automatically — no explicit registration needed.
 *
 * @param {string} settingKey Settings field key from the REST error details.
 * @return {string} DOM element id to look up.
 */
const fieldKeyToInputId = ( settingKey ) =>
	`${ settingKey.replace( /_/g, '-' ) }-input`;

/**
 * Scroll the page so the first field referenced in a server-error `details`
 * object is brought into view and focused, mirroring the way native form
 * validation focuses the offending input. No-ops when no matching DOM element
 * exists.
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

	const reduce = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;
	element.scrollIntoView( {
		behavior: reduce ? 'auto' : 'smooth',
		block: 'center',
	} );
	element.focus( { preventScroll: true } );
};

export default scrollToFirstFieldError;
