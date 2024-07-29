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

	const selectedCountry = businessTypes.find( ( country ) => {
		// Special case for Puerto Rico as it's considered a separate country in Core, but the business country should be US
		if ( data.country === 'PR' ) {
			return country.key === 'US';
		}

		return country.key === data.country;
	} );

	// Reorder the country business types so company is always first, if it exists.
	const reorderedBusinessTypes = selectedCountry?.types.sort( ( a, b ) =>
		// eslint-disable-next-line no-nested-ternary
		a.key === 'company' ? -1 : b.key === 'company' ? 1 : 0
	);

	const selectedBusinessType = reorderedBusinessTypes?.find(
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
					actions={ [
						{
							label: strings.inlineNotice.action,
							onClick: () => handleTiedChange( 'country', null ),
						},
					] }
					status="info"
				>
					<div className="wcpay-inline-notice__content__title">
						{ strings.inlineNotice.title }{ ' ' }
						<b>{ selectedCountry.name }</b>
					</div>
				</InlineNotice>
			) }
			{ ! selectedCountry && (
				<span data-testid={ 'country-select' }>
					<OnboardingSelectField
						name="country"
						options={ countries }
						onChange={ handleTiedChange }
					/>
				</span>
			) }
			{ selectedCountry && selectedCountry.types.length > 0 && (
				<span data-testid={ 'business-type-select' }>
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
				</span>
			) }
			{ selectedBusinessType &&
				selectedBusinessType.structures.length > 0 && (
					<span data-testid={ 'business-structure-select' }>
						<OnboardingSelectField
							name="company.structure"
							options={ selectedBusinessType.structures }
							onChange={ handleTiedChange }
						/>
					</span>
				) }
			{ selectedCountry &&
				selectedBusinessType &&
				selectedBusinessStructure && (
					<span data-testid={ 'mcc-select' }>
						<OnboardingGroupedSelectField
							name="mcc"
							options={ mccsFlatList }
							searchable
						/>
					</span>
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
