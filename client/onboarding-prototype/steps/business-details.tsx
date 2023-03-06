/**
 * External dependencies
 */
import React from 'react';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { useOnboardingContext } from '../context';
import CustomSelectControl, { Item } from 'components/custom-select-control';
import { useBusinessTypes } from 'onboarding-experiment/hooks';
import { OnboardingFields } from '../types';
import { BusinessType } from 'onboarding-experiment/types';

const BusinessDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();
	const { countries } = useBusinessTypes();

	const selectedCountry = countries.find(
		( country ) => country.key === data.country
	);
	const selectedBusinessType = selectedCountry?.types.find(
		( type ) => type.key === data.business_type
	);
	const selectedCompanyStructure = selectedBusinessType?.structures.find(
		( structure ) => structure.key === data[ 'company.structure' ]
	);

	const getFieldProps = ( name: keyof OnboardingFields ) => ( {
		label: strings.fields[ name ],
	} );

	const getTextFieldProps = ( name: keyof OnboardingFields ) => ( {
		...getFieldProps( name ),
		value: data[ name ] || '',
		onChange: ( value: string ) => setData( { [ name ]: value } ),
	} );

	const getSelectFieldProps = (
		name: keyof Pick<
			OnboardingFields,
			'country' | 'business_type' | 'company.structure' | 'mcc'
		>
	) => ( {
		...getFieldProps( name ),
		placeholder: strings.placeholders[ name ],
		onChange: ( { selectedItem }: { selectedItem?: Item } ) => {
			let newData: OnboardingFields = {
				[ name ]: selectedItem?.key,
			};
			if ( name === 'business_type' ) {
				newData = { ...newData, 'company.structure': undefined };
			} else if ( name === 'country' ) {
				newData = { ...newData, business_type: undefined };
			}
			setData( newData );
		},
	} );

	return (
		<>
			<TextControl { ...getTextFieldProps( 'business_name' ) } />
			<TextControl { ...getTextFieldProps( 'url' ) } />
			<CustomSelectControl
				{ ...getSelectFieldProps( 'country' ) }
				value={ selectedCountry }
				options={ countries }
			/>
			{ selectedCountry && selectedCountry.types.length > 0 && (
				<CustomSelectControl
					{ ...getSelectFieldProps( 'business_type' ) }
					value={ selectedBusinessType }
					options={ selectedCountry.types }
				>
					{ ( item: Item & BusinessType ) => (
						<div>
							<div>{ item.name }</div>
							<div className="complete-business-info-task__option-description">
								{ item.description }
							</div>
						</div>
					) }
				</CustomSelectControl>
			) }
			{ selectedBusinessType &&
				selectedBusinessType.structures.length > 0 && (
					<CustomSelectControl
						{ ...getSelectFieldProps( 'company.structure' ) }
						value={ selectedCompanyStructure }
						options={ selectedBusinessType.structures }
					/>
				) }
			<CustomSelectControl
				{ ...getSelectFieldProps( 'mcc' ) }
				// @ismaeldcom TODO: The select control must provide search functionality.
				// @ismaeldcom TODO: Populate MCC options
				options={ [] }
			/>
		</>
	);
};

export default BusinessDetails;
