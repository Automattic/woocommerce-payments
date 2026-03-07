/**
 * Recursively appends a nested object to a FormData instance using bracket
 * notation (e.g. `appearance[rules][.Input][color]`).
 *
 * @param {FormData} formData The FormData to append to.
 * @param {Object}   obj      The object to serialize.
 * @param {string}   prefix   Root key name (default: 'appearance').
 */
export const appendObjectToFormData = (
	formData,
	obj,
	prefix = 'appearance'
) => {
	Object.entries( obj ).forEach( ( [ key, value ] ) => {
		const fieldKey = `${ prefix }[${ key }]`;

		// Skip null/undefined to avoid FormData coercing them to strings.
		if ( value === null || typeof value === 'undefined' ) {
			return;
		}

		if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
			appendObjectToFormData( formData, value, fieldKey );
		} else {
			formData.append( fieldKey, value );
		}
	} );
};
