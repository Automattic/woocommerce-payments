/**
 * Internal dependencies
 */
import businessTypeStrings from 'onboarding-experiment/translations/types';
import businessStructureStrings from 'onboarding-experiment/translations/structures';
import businessTypeDescriptionStrings from 'onboarding-experiment/translations/descriptions';

type CountriesData = Country[];

export const getBusinessTypes = (): CountriesData => {
	const data = wcpaySettings?.onboardingFieldsData?.business_types;

	return (
		( data || [] )
			.map( ( country ) => ( {
				key: country.key,
				name: country.name,
				types: country.types.map( ( type ) => ( {
					key: type.key,
					name: businessTypeStrings[ type.key ],
					description: businessTypeDescriptionStrings[ country.key ]
						? businessTypeDescriptionStrings[ country.key ][
								type.key
						  ]
						: businessTypeDescriptionStrings.generic[ type.key ],
					structures: type.structures.map( ( structure ) => ( {
						key: structure.key,
						name:
							businessStructureStrings[ country.key ][
								structure.key
							],
					} ) ),
				} ) ),
			} ) )
			.sort( ( a, b ) => a.name.localeCompare( b.name ) ) || []
	);
};
