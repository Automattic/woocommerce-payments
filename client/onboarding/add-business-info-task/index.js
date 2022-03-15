/**
 * External dependencies
 */
import React, { useState } from 'react';
//  import { useEffect, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
// import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';

const AddBusinessInfo = () => {
	const [ country, businessType, setCountry, setBusinessType ] = useState();

	return (
		<WizardTaskItem
			className="complete-business-info-task"
			index={ 1 }
			title={ __(
				'Tell us more about your business',
				'woocommerce-payments'
			) }
			visibleDescription={ __(
				"Preview the details we'll require to verify your business and enable deposits.",
				'woocommerce-payments'
			) }
		>
			<SelectControl
				label={ __( 'Country', 'woocommerce-payments' ) }
				value={ country }
				onChange={ ( value ) => setCountry( value ) }
				options={ [
					{ label: 'United States', value: 'US' },
					{
						label: 'United Kingdom',
						value: 'UK',
					},
				] }
			/>

			<p>
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
		</WizardTaskItem>
	);
};

export default AddBusinessInfo;
