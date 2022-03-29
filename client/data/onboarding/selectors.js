/** @format */

/**
 * Internal dependencies
 */
import strings from 'onboarding/strings';

export const getOnboardingState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.onboarding || {};
};

export const getBusinessTypes = ( state ) => {
	const businessTypes = getOnboardingState( state ).data || [];

	return businessTypes.map( ( country ) => {
		const types = country.types.map( ( type ) => {
			const structures = type.structures.map( ( structure ) => {
				return {
					key: structure.key,
					name:
						strings.businessStructures[ country.key ][
							structure.key
						],
				};
			} );

			return {
				key: type.key,
				name: strings.businessTypes[ type.key ],
				description: strings.businessTypeDescriptions.hasOwnProperty(
					country.key
				)
					? strings.businessTypeDescriptions[ country.key ][
							type.key
					  ]
					: strings.businessTypeDescriptions.generic[ type.key ],
				structures: structures,
			};
		} );

		return {
			key: country.key,
			name: country.name,
			types: types,
		};
	} );
};
