/**
 * External dependencies
 */
import React, { useState } from 'react';
//  import { useEffect, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl, Card, CardBody } from '@wordpress/components';
// import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';

const AddBusinessInfo = () => {
	const { countries } = wcSettings;

	const {
		connect: { country },
	} = wcpaySettings;

	const allCountries = Object.keys( countries ).map( ( countryCode ) => {
		return { label: countries[ countryCode ], value: countryCode };
	} );

	// const wcpayEnabledCountries = Object.values( availableCountries ).sort();

	const [ businessType, setCountry, setBusinessType ] = useState();

	return (
		<WizardTaskItem
			className="complete-business-info-task"
			index={ 1 }
			title={ __(
				'Tell us more about your business',
				'woocommerce-payments'
			) }
		>
			<p className="wcpay-wizard-task__description-element subheading is-muted-color">
				{ __(
					"Preview the details we'll require to verify your business and enable despoits.",
					'woocommerce-payments'
				) }
			</p>

			<SelectControl
				label={ __( 'Country', 'woocommerce-payments' ) }
				value={ country }
				onChange={ ( value ) => setCountry( value ) }
				options={ allCountries }
			/>

			<p className="wcpay-wizard-task__description-element is-muted-color">
				{ __(
					'The primary country where your business operates',
					'woocommerce-payments'
				) }
			</p>

			<SelectControl
				label={ __( 'Business type', 'woocommerce-payments' ) }
				value={ businessType }
				onChange={ ( value ) => setBusinessType( value ) }
				options={ [
					{
						label: 'Individual',
						value: 'individual',
					},
					{ label: 'Company', value: 'company' },
					{
						label: 'Non-profit Organisation',
						value: 'nonprofit_organisation',
					},
				] }
			/>

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
