/**
 * Internal dependencies
 */
import businessTypeStrings from 'onboarding-experiment/translations/types';
import businessStructureStrings from 'onboarding-experiment/translations/structures';
import businessTypeDescriptionStrings from 'onboarding-experiment/translations/descriptions';
import { ListItem } from 'components/grouped-select-control';

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

export const getMccsFlatList = (): ListItem[] => {
	const data = wcpaySettings?.onboardingFieldsData?.mccs_display_tree;

	// Right now we support only two levels (top-level groups and items in those groups).
	// For safety, we will discard anything else like top-level items or sub-groups.
	const normalizedData = ( data || [] ).filter( ( group ) => {
		if ( ! group?.type || 'group' !== group.type ) {
			return false;
		}

		const groupItems =
			group.items?.filter(
				( item ) => item?.type && 'mcc' === item.type
			) || [];

		return groupItems.length;
	} );

	return normalizedData.reduce( ( acc, group ): ListItem[] => {
		const groupItems =
			group.items?.map(
				( item ): ListItem => {
					return {
						type: 'option',
						key: item.id,
						name: item.title,
						group: group.id,
						context: item?.keywords
							? item.keywords.join( ' ' )
							: '',
					};
				}
			) || [];

		return [
			...acc,
			{
				type: 'group',
				key: group.id,
				name: group.title,
				items: groupItems.map( ( item ) => item.key ),
			},
			...groupItems,
		];
	}, [] as ListItem[] );
};
