/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from '../context';
import { Item } from 'components/custom-select-control';
import GroupedSelectControl, {
	ListItem,
} from 'components/grouped-select-control';
import { OnboardingFields } from '../types';
import { OnboardingTextField, OnboardingSelectField } from '../form';
import {
	getBusinessTypes,
	getMccsFlatList,
} from 'wcpay/onboarding-experiment/utils';

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
		selectedItem?: Item
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
	const selectedMcc =
		mccsFlatList.find( ( item ) => item.key === data.mcc ) ||
		( {} as ListItem );

	const handleMccChange = ( selectedItem?: ListItem ) => {
		const newData: OnboardingFields = {
			mcc: selectedItem?.key,
		};
		setData( newData );
	};

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

			<GroupedSelectControl
				label="Chose your industry"
				options={ mccsFlatList }
				onChange={ handleMccChange }
				value={ selectedMcc }
				searchable
				placeholder="Select an option"
			/>
		</>
	);
};

export default BusinessDetails;
