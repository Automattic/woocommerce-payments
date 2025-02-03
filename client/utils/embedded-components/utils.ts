/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import { addQueryArgs } from '@wordpress/url';
import { AccountKycSession } from '../../onboarding/types';

/**
 * Make an API request to create an account session.
 */
export const createAccountSession = async (): Promise< AccountKycSession > => {
	return await apiFetch< AccountKycSession >( {
		path: addQueryArgs( `${ NAMESPACE }/accounts/session`, {} ),
		method: 'GET',
	} );
};
