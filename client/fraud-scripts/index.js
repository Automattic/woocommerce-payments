/**
 * Internal dependencies
 */
import sift from './sift';
import { ensureStripeJsScript } from 'wcpay/utils/stripe-js';

const services = {
	sift,
	stripe: ensureStripeJsScript,
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
