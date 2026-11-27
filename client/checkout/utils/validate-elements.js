/**
 * Validates the Stripe elements by submitting them and handling any errors that occur during submission.
 *
 * @param {Object} elements The Stripe elements object to be validated.
 * @return {Promise} Promise for the checkout submission. Rejects with an Error carrying Stripe's message and error `type`.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			const error = new Error( result.error.message );
			error.type = result.error.type;
			throw error;
		}
	} );
}
