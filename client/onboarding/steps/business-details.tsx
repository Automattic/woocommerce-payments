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
import { OnboardingGroupedSelectField, OnboardingSelectField } from '../form';
import {
	getAvailableCountries,
	getBusinessTypes,
	getMccsFlatList,
} from 'onboarding/utils';
import { BusinessType } from 'onboarding/types';
import InlineNotice from 'components/inline-notice';
import strings from 'onboarding/strings';

const BusinessDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();
	const countries = getAvailableCountries();
	const businessTypes = getBusinessTypes();
	const mccsFlatList = getMccsFlatList();

	const selectedCountry = businessTypes.find(
		( country ) => country.key === data.country
	);
	const selectedBusinessType = selectedCountry?.types.find(
		( type ) => type.key === data.business_type
	);

	const selectedBusinessStructure =
		selectedBusinessType?.structures.length === 0 ||
		selectedBusinessType?.structures.find(
			( structure ) => structure.key === data[ 'company.structure' ]
		);

	const selectedMcc = mccsFlatList.find( ( mcc ) => mcc.key === data.mcc );

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

	return (
		<>
			{ selectedCountry && (
				<InlineNotice
					isDismissible={ false }
					buttonVariant={ 'link' }
					className={ 'wcpay-onboarding__inline-notice' }
					actions={ [
						{
							label: strings.inlineNotice.action,
							onClick: () => handleTiedChange( 'country', null ),
						},
					] }
					status="info"
				>
					<span>
						{ strings.inlineNotice.title }{ ' ' }
						<b>{ selectedCountry.name }</b>
					</span>
				</InlineNotice>
			) }
			{ ! selectedCountry && (
				<OnboardingSelectField
					name="country"
					options={ countries }
					onChange={ handleTiedChange }
				/>
			) }
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
			{ selectedCountry &&
				selectedBusinessType &&
				selectedBusinessStructure && (
					<OnboardingGroupedSelectField
						name="mcc"
						options={ mccsFlatList }
						searchable
					/>
				) }

			{ selectedCountry &&
				selectedBusinessType &&
				selectedBusinessStructure &&
				selectedMcc && (
					<span className={ 'wcpay-onboarding__tos' }>
						{ strings.tos }
					</span>
				) }
		</>
	);
};

export default BusinessDetails;
