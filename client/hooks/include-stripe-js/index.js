/**
 * External dependencies
 */
import { useEffect } from 'react';

/**
 * Internal dependencies
 */

const includeStripeJS = () => {
	useEffect( () => {
		const script = document.createElement( 'script' );

		script.src = 'https://js.stripe.com/v3';
		script.async = true;

		document.body.appendChild( script );

		return () => {
			document.body.removeChild( script );
		};
	}, [] );
};

export default includeStripeJS;
