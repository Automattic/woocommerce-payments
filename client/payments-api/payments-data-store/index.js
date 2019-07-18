
/**
 * External dependencies.
 */
import { registerGenericStore } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import createApiClient from './create-api-client';
import paymentsApiSpec from '../api-spec/payments-rest-api';

if ( 'development' === process.env.NODE_ENV ) {
	window.__FRESH_DATA_DEV_INFO__ = true;
}

function createPaymentsApiStore() {
	const apiClient = createApiClient( 'wc-payments-api', paymentsApiSpec );

	return {
		// The wrapped function for getSelectors is temporary code.
		//
		// @todo Remove the `() =>` after the `@wordpress/data` PR is merged:
		// https://github.com/WordPress/gutenberg/pull/11460
		//
		getSelectors: () => {
			return apiClient.getSelectors();
		},
		getActions: () => {
			return apiClient.getMutations();
		},
		subscribe: apiClient.subscribe,
	};
}

registerGenericStore( 'wc-payments-api', createPaymentsApiStore() );
