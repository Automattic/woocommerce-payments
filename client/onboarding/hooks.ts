/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { NAMESPACE } from 'data/constants';

/**
 * Internal dependencies
 */
import { useApiFetch } from 'hooks/use-api-fetch';
import { AccountSession, OnboardingProps } from './types';

type AccountSessionData = AccountSession;

interface AccountSessionReturn {
	accountSession: AccountSession;
	isLoading: boolean;
}

export const useAccountSession = (
	query: OnboardingProps
): AccountSessionReturn => {
	const path = addQueryArgs( `${ NAMESPACE }/onboarding/embedded`, query );
	const { data, isLoading } = useApiFetch< AccountSessionData >( {
		path,
		errorMessage: __(
			'Error creating account session',
			'woocommerce-payments'
		),
	} );

	return {
		accountSession: {
			clientSecret: data?.clientSecret || '',
			expiresAt: data?.expiresAt || 0,
			accountId: data?.accountId || '',
			isLive: data?.isLive || false,
			accountCreated: data?.accountCreated || false,
		},
		isLoading,
	};
};
