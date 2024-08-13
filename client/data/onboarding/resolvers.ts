/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { TYPES } from './types';
import { NAMESPACE, STORE_NAME } from '../constants';

export const resolvers = {
	*initEmbeddedOnboarding(
		id: string,
		additionalData: Record< string, any >
	): Generator< unknown > {
		try {
			const accountSession = yield apiFetch( {
				path: `${ NAMESPACE }/onboarding/embedded`,
				method: 'POST',
				data: { id, ...additionalData },
			} );

			yield dispatch( STORE_NAME ).receiveAccountSession(
				accountSession
			);

			return {
				type: TYPES.INIT_EMBEDDED_ONBOARDING,
				accountSession,
			};
		} catch ( error ) {
			// TODO: handle.
		}
	},
};
