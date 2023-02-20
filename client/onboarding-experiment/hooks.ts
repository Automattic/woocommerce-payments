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
import { Country, OnboardingProps } from './types';
import businessTypeStrings from 'onboarding-experiment/translations/types';
import businessStructureStrings from 'onboarding-experiment/translations/structures';
import businessTypeDescriptionStrings from 'onboarding-experiment/translations/descriptions';

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

type CountriesData = Country[];

interface CountriesReturn {
	countries: CountriesData;
	isLoading: boolean;
}

export const useBusinessTypes = (): CountriesReturn => {
	const path = `${ NAMESPACE }/onboarding/business_types`;
	const { data, isLoading } = useApiFetch< CountriesData >( {
		path,
		errorMessage: __(
			'Error retrieving business types',
			'woocommerce-payments'
		),
	} );

	return {
		countries:
			( data || [] )
				.map( ( country ) => ( {
					key: country.key,
					name: country.name,
					types: country.types.map( ( type ) => ( {
						key: type.key,
						name: businessTypeStrings[ type.key ],
						description: businessTypeDescriptionStrings[
							country.key
						]
							? businessTypeDescriptionStrings[ country.key ][
									type.key
							  ]
							: businessTypeDescriptionStrings.generic[
									type.key
							  ],
						structures: type.structures.map( ( structure ) => ( {
							key: structure.key,
							name:
								businessStructureStrings[ country.key ][
									structure.key
								],
						} ) ),
					} ) ),
				} ) )
				.sort( ( a, b ) => a.name.localeCompare( b.name ) ) || [],
		isLoading,
	};
};
