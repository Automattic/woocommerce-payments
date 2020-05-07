/**
 * External dependencies
 */
import { useEffect } from 'react';

/**
 * Internal dependencies
 */

const includeStripeJS = () => {
	useEffect( () => {
		const scriptId = 'wcpaystripejs';
		const script = document.createElement( 'script' );
		script.src = 'https://js.stripe.com/v3';
		script.async = true;
		script.id = scriptId;

		if ( ! document.getElementById( scriptId ) ) {
			document.body.appendChild( script );
		}

		return () => {};
	}, [] );
};

export default includeStripeJS;
