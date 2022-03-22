/** @format */

/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
//  import { useEffect, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl, Card, CardBody } from '@wordpress/components';
// import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'wcpay/additional-methods-setup/wizard/task/context';
import OnboardingSelectControl from 'wcpay/components/onboarding-select-control';
import { useBusinessTypes } from 'wcpay/data/onboarding';
import strings from '../strings';

const AddBusinessInfo = () => {
	const { setCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();
	const {
		connect: { availableCountries, country },
	} = wcpaySettings;

	const countries = Object.keys( availableCountries ).map(
		( countryCode ) => {
			return {
				label: availableCountries[ countryCode ],
				value: countryCode,
			};
		}
	);

	const [ businessCountry, setBusinessCountry ] = useState( country );
	const [ businessType, setBusinessType ] = useState();
	const [ businessStructure, setBusinessStructure ] = useState();
	const [ displayStructures, setDisplayStructures ] = useState( false );

	let businessTypeSelectValues = [];
	let businessStructureSelectValues = [];

	const getBusinessTypesForCountry = ( countryCode ) => {
		if ( isLoading || ! businessTypes.hasOwnProperty( 'data' ) ) {
			return false;
		}

		let result = false;

		// TODO: It's probably better if this is an object where the country codes are keys, makes it easier to traverse.
		businessTypes.data.forEach( ( element ) => {
			if ( element.country_code === countryCode ) {
				result = element;
			}
		} );

		// Return false if the country isn't in our array.
		return result;
	};

	const formatBusinessTypes = ( countryCode ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return [];
		}

		const arr = [];

		businessTypeData.types.forEach( ( element ) => {
			arr.push( {
				name: strings.businessTypes[ element.type ],
				description: 'The description should be populated here',
				key: element.type,
			} );
		} );

		businessTypeSelectValues = arr;
	};

	const shouldDisplayStructures = ( countryCode, type ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return false;
		}

		let structures = [];

		businessTypeData.types.forEach( ( element ) => {
			if ( element.type === type ) {
				structures = element.structures;
			}
		} );

		return 0 < structures.length;
	};

	const formatBusinessStructures = ( countryCode, type ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return false;
		}

		let structures = [];

		businessTypeData.types.forEach( ( element ) => {
			if ( element.type === type ) {
				structures = element.structures;
			}
		} );

		const arr = [];

		structures.forEach( ( el ) => {
			arr.push( {
				label: el.label,
				key: el.key,
			} );
		} );

		businessStructureSelectValues = structures;
	};

	const handleBusinessTypeUpdate = ( type ) => {
		// TODO: first, check if this particular country/business type combo needs to show the structure select - then show it.
		// TODO: Only set completed if we need to - otherwise, do this on the setBusinessStructure.
		setBusinessType( type );

		if ( shouldDisplayStructures( businessCountry, type ) ) {
			formatBusinessStructures( businessCountry, type );
			setDisplayStructures( true );
		} else {
			setDisplayStructures( false );
			setCompleted( true );
		}
	};

	const handleBusinessCountryUpdate = ( countryVal ) => {
		setBusinessCountry( countryVal );
		formatBusinessTypes( countryVal );
		setCompleted( false );
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		// TODO: Mark setCompleted as true, show the verification requirements.
		setBusinessStructure( structure );
		setCompleted( true );
	};

	return (
		<WizardTaskItem
			className="complete-business-info-task"
			index={ 1 }
			title={ strings.onboarding.heading }
		>
			{ isLoading ? <p>Loading...</p> : '' }

			<p className="wcpay-wizard-task__description-element subheading is-muted-color">
				{ strings.onboarding.description }
			</p>

			<SelectControl
				label={ __( 'Country', 'woocommerce-payments' ) }
				value={ businessCountry }
				onChange={ ( value ) => handleBusinessCountryUpdate( value ) }
				options={ countries }
			/>

			<p className="wcpay-wizard-task__description-element is-muted-color">
				{ strings.onboarding.countryDescription }
			</p>

			{ isLoading ? null : (
				<OnboardingSelectControl
					className="wcpay-onboarding-select-business-types"
					label={ __( 'Business type', 'woocommerce-payments' ) }
					value={ businessType }
					onChange={ ( value ) => handleBusinessTypeUpdate( value ) }
					options={ businessTypeSelectValues }
				/>
			) }

			{ displayStructures ? (
				<SelectControl
					label={ __( 'Business structure', 'woocommerce-payments' ) }
					value={ businessStructure }
					onChange={ ( value ) =>
						handleBusinessStructureUpdate( value )
					}
					options={ businessStructureSelectValues }
				/>
			) : null }

			<Card size="large" className="wcpay-required-info-card">
				<CardBody>
					<p>
						<b>
							{ __(
								"To verify your details, we'll require:",
								'woocommerce-payments'
							) }
						</b>
					</p>
				</CardBody>
			</Card>
		</WizardTaskItem>
	);
};

export default AddBusinessInfo;
