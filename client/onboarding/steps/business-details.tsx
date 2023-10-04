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
import { getBusinessTypes, getMccsFlatList } from 'onboarding/utils';
import { BusinessType } from 'onboarding/types';

const BusinessDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();
	const countries = getBusinessTypes();

	const selectedCountry = countries.find(
		( country ) => country.key === data.country
	);
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
			<OnboardingTextField
				className="business-details__name"
				name="business_name"
			/>
			<OnboardingTextField className="business-details__url" name="url" />
			<OnboardingSelectField
				name="country"
				className="business-details__country"
				options={ countries }
				onChange={ handleTiedChange }
			/>
			{ selectedCountry && selectedCountry.types.length > 0 && (
				<OnboardingSelectField
					name="business_type"
					className="business-details__type"
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
				className="business-details__mcc"
				options={ mccsFlatList }
				searchable
			/>
		</>
	);
};

export default BusinessDetails;
