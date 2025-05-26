/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'data/constants';
import { AccountSession } from './types';
import { OnboardingFields } from 'wcpay/onboarding/types';
import { fromDotNotation } from 'wcpay/onboarding/utils';

/**
 * Make an API request to create an account session.
 */
export const createAccountSession = async (): Promise< AccountSession > => {
	return await apiFetch< AccountSession >( {
		path: addQueryArgs( `${ NAMESPACE }/accounts/session`, {} ),
		method: 'GET',
	} );
};

/**
 * Make an API request to create an KYC account session.
 *
 * @param data The form data.
 */
export const createKycAccountSession = async (
	data: OnboardingFields
): Promise< AccountSession > => {
	const urlParams = new URLSearchParams( window.location.search );

	return await apiFetch< AccountSession >( {
		path: `${ NAMESPACE }/onboarding/kyc/session`,
		method: 'POST',
		data: {
			self_assessment: fromDotNotation( data ),
			capabilities: urlParams.get( 'capabilities' ) || '',
		},
	} );
};
