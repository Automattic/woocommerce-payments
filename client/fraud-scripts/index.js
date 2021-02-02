/**
 * Internal dependencies
 */
import * as sift from './sift';
import * as stripe from './stripe';

const services = {
	sift,
	stripe,
};

export default ( config ) => {
	for ( const serviceName in config ) {
		const service = services[ serviceName ];
		if ( ! service || ! config[ serviceName ] ) {
			continue;
		}

		if ( service.init ) {
			service.init( config[ serviceName ] );
		}

		if ( ! document.querySelector( `[src="${ service.src }"]` ) ) {
			const script = document.createElement( 'script' );
			script.src = service.src;
			script.async = true;
			document.body.appendChild( script );
		}
	}
};
