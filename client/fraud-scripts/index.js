/**
 * Internal dependencies
 */
import forter from './forter';
import sift from './sift';
import stripe from './stripe';

const services = {
	forter,
	sift,
	stripe,
};

export default ( config ) => {
	for ( const serviceName in config ) {
		const service = services[ serviceName ];
		if ( ! service || ! config[ serviceName ] ) {
			continue;
		}

		service( config[ serviceName ] );
	}
};
