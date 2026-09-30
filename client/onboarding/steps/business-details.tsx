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
import strings from 'onboarding/strings';

/**
 * Contains business and store details KYC logic.
 */
const BusinessDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();

	// These read from wcpaySettings, which the page localises once and never changes.
	const countries = React.useMemo( () => getAvailableCountries(), [] );
	const businessTypes = React.useMemo( () => getBusinessTypes(), [] );
	const mccsFlatList = React.useMemo( () => getMccsFlatList(), [] );

	const selectedCountry = businessTypes.find( ( country ) => {
		// Special case for Puerto Rico as it's considered a separate country in Core, but the business country should be US.
		if ( data.country === 'PR' ) {
			return country.key === 'US';
		}

		return country.key === data.country;
	} );

	// Reorder the country business types so company is always first, if it exists.
	// Sort on a copy — the source list is built once and shared across renders.
	const reorderedBusinessTypes = selectedCountry
		? [ ...selectedCountry.types ].sort( ( a, b ) =>
				// eslint-disable-next-line no-nested-ternary
				a.key === 'company' ? -1 : b.key === 'company' ? 1 : 0
		  )
		: undefined;

	const selectedBusinessType = reorderedBusinessTypes?.find(
		( type ) => type.key === data.business_type
	);

	const selectedBusinessStructures = selectedBusinessType?.structures ?? [];
	const shouldDisplayBusinessStructure =
		selectedBusinessStructures.length > 0 &&
		selectedBusinessType?.requires_structure !== false &&
		! (
			selectedBusinessStructures.length === 1 &&
			selectedBusinessStructures[ 0 ].key === 'nil'
		);
	const selectedBusinessStructure =
		! shouldDisplayBusinessStructure ||
		selectedBusinessStructures.find(
			( structure ) => structure.key === data[ 'company.structure' ]
		);

	React.useEffect( () => {
		// handleTiedChange clears the structure when the merchant changes business type.
		// This catches cached/initial structure values for business types whose structure field
		// is hidden.
		if (
			selectedBusinessType &&
			! shouldDisplayBusinessStructure &&
			data[ 'company.structure' ]
		) {
			setData( { 'company.structure': undefined } );
		}
	}, [
		data,
		selectedBusinessType,
		setData,
		shouldDisplayBusinessStructure,
	] );

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
			<span data-testid={ 'country-select' }>
				<OnboardingSelectField
					name="country"
					options={ countries }
					onChange={ handleTiedChange }
				/>
			</span>
			{ reorderedBusinessTypes && reorderedBusinessTypes.length > 0 && (
				<span data-testid="business-type-select">
					<OnboardingSelectField
						name="business_type"
						options={ reorderedBusinessTypes }
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
			{ selectedBusinessType && shouldDisplayBusinessStructure && (
				<span data-testid={ 'business-structure-select' }>
					<OnboardingSelectField
						name="company.structure"
						options={ selectedBusinessStructures }
						onChange={ handleTiedChange }
					/>
				</span>
			) }
			{ selectedCountry &&
				selectedBusinessType &&
				selectedBusinessStructure && (
					<>
						<span data-testid={ 'mcc-select' }>
							<OnboardingGroupedSelectField
								name="mcc"
								options={ mccsFlatList }
								searchable
							/>
						</span>
						<span className={ 'wcpay-onboarding__tos' }>
							{ strings.tos }
						</span>
					</>
				) }
		</>
	);
};

export default BusinessDetails;
