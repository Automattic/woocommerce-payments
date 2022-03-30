/** @format */

/**
 * Internal dependencies
 */
import { Country, State } from './types';
import businessTypeStrings from 'wcpay/onboarding/translations/types';
import businessStructureStrings from 'wcpay/onboarding/translations/structures';
import businessTypeDescriptionStrings from 'wcpay/onboarding/translations/descriptions';

export const getBusinessTypes = ( state: State ): Country[] => {
	return state.onboarding.countries.map( ( country ) => ( {
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

export const getRequiredVerificationInfo = ( state: State ): string[] => {
	return state.onboarding.required_fields ?? [];
};
