/** @format */

/**
 * Internal dependencies
 */
import { Country, OnboardingState, State } from './types';
import businessTypeStrings from 'wcpay/onboarding/translations/types';
import businessStructureStrings from 'wcpay/onboarding/translations/structures';
import businessTypeDescriptionStrings from 'wcpay/onboarding/translations/descriptions';

export const getOnboardingState = ( state: State ): OnboardingState => {
	if ( ! state ) {
		return {};
	}

	return state.onboarding || {};
};

export const getBusinessTypes = ( state: State ): Country[] => {
	const businessTypes = getOnboardingState( state ).data || [];

	return businessTypes.map( ( country ) => ( {
		key: country.key,
		name: country.name,
		types: country.types.map( ( type ) => ( {
			key: type.key,
			name: businessTypeStrings[ type.key ],
			description: businessTypeDescriptionStrings[ country.key ]
				? businessTypeDescriptionStrings[ country.key ][ type.key ]
				: businessTypeDescriptionStrings.generic[ type.key ],
			structures: type.structures.map( ( structure ) => ( {
				key: structure.key,
				name: businessStructureStrings[ country.key ][ structure.key ],
			} ) ),
		} ) ),
	} ) );
};
