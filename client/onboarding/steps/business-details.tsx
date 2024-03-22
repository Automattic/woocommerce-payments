/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from '../context';
import { Item } from 'components/custom-select-control';
import { OnboardingFields } from '../types';
import {
	OnboardingGroupedSelectField,
	OnboardingSelectField,
	OnboardingTextField,
} from '../form';
import {
	getAvailableCountries,
	getBusinessTypes,
	getMccsFlatList,
} from 'onboarding/utils';
import { BusinessType } from 'onboarding/types';

const BusinessDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();
	const countries = getAvailableCountries();
	const businessTypes = getBusinessTypes();

	const selectedCountry = businessTypes.find( ( country ) => {
		// Special case for Puerto Rico as it's considered a separate country in Core, but the business country should be US
		if ( data.country === 'PR' ) {
			return country.key === 'US';
		}

		return country.key === data.country;
	} );

	const selectedBusinessType = selectedCountry?.types.find(
		( type ) => type.key === data.business_type
	);

	const handleTiedChange = (
		name: keyof OnboardingFields,
		selectedItem?: Item | null
	) => {
		let newData: OnboardingFields = {
			[ name ]: selectedItem?.key,
		};
		if ( name === 'business_type' ) {
			newData = { ...newData, 'company.structure': undefined };
		} else if ( name === 'country' ) {
			newData = { ...newData, business_type: undefined };
		}
		setData( newData );
	};

	const mccsFlatList = getMccsFlatList();

	return (
		<>
			<OnboardingTextField name="business_name" />
			<OnboardingTextField name="url" />
			<OnboardingSelectField
				name="country"
				options={ countries }
				onChange={ handleTiedChange }
			/>
			{ selectedCountry && selectedCountry.types.length > 0 && (
				<OnboardingSelectField
					name="business_type"
					options={ selectedCountry.types }
					onChange={ handleTiedChange }
				>
					{ ( item: Item & BusinessType ) => (
						<div>
							<div>{ item.name }</div>
							<div className="complete-business-info-task__option-description">
								{ item.description }
							</div>
						</div>
					) }
				</OnboardingSelectField>
			) }
			{ selectedBusinessType &&
				selectedBusinessType.structures.length > 0 && (
					<OnboardingSelectField
						name="company.structure"
						options={ selectedBusinessType.structures }
						onChange={ handleTiedChange }
					/>
				) }

			<OnboardingGroupedSelectField
				name="mcc"
				options={ mccsFlatList }
				searchable
			/>
		</>
	);
};

export default BusinessDetails;
