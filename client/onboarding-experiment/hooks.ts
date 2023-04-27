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
import { OnboardingProps } from './types';

type VerificationInfoData = string[];

interface VerificationInfoReturn {
	requiredInfo: VerificationInfoData;
	isLoading: boolean;
}

export const useRequiredVerificationInfo = (
	query: OnboardingProps
): VerificationInfoReturn => {
	const path = addQueryArgs(
		`${ NAMESPACE }/onboarding/required_verification_information`,
		query
	);
	const { data, isLoading } = useApiFetch< VerificationInfoData >( {
		path,
		errorMessage: __(
			'Error retrieving required verification information',
			'woocommerce-payments'
		),
	} );

	return {
		requiredInfo: data || [],
		isLoading,
	};
};
