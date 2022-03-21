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
// import { useBusinessTypes } from 'wcpay/data/onboarding';
import strings from '../strings';

const AddBusinessInfo = () => {
	const { setCompleted } = useContext( WizardTaskContext );
	// const { businessTypes, isLoading } = useBusinessTypes();
	const isLoading = false;
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

	const handleBusinessTypeUpdate = ( type ) => {
		// TODO: first, check if this particular country/business type combo needs to show the structure select - then show it.
		// TODO: Only set completed if we need to - otherwise, do this on the setBusinessStructure.
		setBusinessType( type );
		setCompleted( true, 'setup-complete' );
		setDisplayStructures( true );
	};

	const handleBusinessCountryUpdate = ( countryVal ) => {
		// TODO: Update the business type display based on whatever is selected here.
		setBusinessCountry( countryVal );
		setCompleted( true );
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		// TODO: Mark setCompleted as true, show the verification requirements.
		setBusinessStructure( structure );
		setCompleted( true, 'setup-complete' );
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

			<OnboardingSelectControl
				className="wcpay-onboarding-select-business-types"
				label={ __( 'Business type', 'woocommerce-payments' ) }
				value={ businessType }
				onChange={ ( value ) => handleBusinessTypeUpdate( value ) }
				options={
					// TODO: this should be a variable updated when the country is changed.
					[
						{
							name: 'Individual',
							description:
								'Select if you run your own business as an individual and are self-employed',
							key: 'individual',
						},
						{
							name: 'Company',
							description:
								'Select if you filed documentation to register your business with a government entity',
							key: 'company',
						},
						{
							name: 'Non-profit Organisation',
							description:
								'Select if you have been granted tax-exempt status by the Internal Revenue Service (IRS)',
							key: 'nonprofit_organisation',
						},
					]
				}
			/>

			{ displayStructures ? (
				<SelectControl
					label={ __( 'Business structure', 'woocommerce-payments' ) }
					value={ businessStructure }
					onChange={ ( value ) =>
						handleBusinessStructureUpdate( value )
					}
					options={ [
						{
							label: 'Test',
							value: 'Test 123',
						},
					] }
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
