/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

const includeStripeJS = () => {
	useEffect( () => {
		const script = document.createElement( 'script' );
		script.src = 'https://js.stripe.com/v3';
		script.async = true;

		if ( ! document.querySelector( `[src="${ script.src }"]` ) ) {
			document.body.appendChild( script );
		}
	}, [] );
};

export default includeStripeJS;
