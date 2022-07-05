/** @format */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, Notice } from '@wordpress/components';
import PhoneNumberInput from 'settings/phone-input';

/**
 * Internal dependencies
 */
import {
	useGetSavingError,
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
} from '../../../data';

const ContactDetailsSection = ( { setSaveDisabled } ) => {
	const [
		accountBusinessSupportEmail,
		setAccountBusinessSupportEmail,
	] = useAccountBusinessSupportEmail();

	const [
		accountBusinessSupportPhone,
		setAccountBusinessSupportPhone,
	] = useAccountBusinessSupportPhone();

	let businessSupportEmailErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_email?.message;

	let businessSupportPhoneErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	const [ isPhoneValid, setPhoneValidity ] = useState( true );

	if ( '' === accountBusinessSupportEmail ) {
		businessSupportEmailErrorMessage = __(
			'Support email cannot be empty, please specify.',
			'woocommerce-payments'
		);
	}

	if ( '' === accountBusinessSupportPhone ) {
		businessSupportPhoneErrorMessage = __(
			'Support phone number cannot be empty, please specify.',
			'woocommerce-payments'
		);
	}

	if ( ! isPhoneValid ) {
		businessSupportPhoneErrorMessage = __(
			'Please enter a valid mobile phone number.',
			'woocommerce-payments'
		);
	}

	const updateSaveButtonAvailability = () => {
		setSaveDisabled(
			'' === accountBusinessSupportEmail ||
				'' === accountBusinessSupportPhone ||
				! isPhoneValid
		);
	};

	return (
		<>
			<h4>
				{ __( 'Customer support contacts', 'woocommerce-payments' ) }
			</h4>
			{ businessSupportEmailErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ businessSupportEmailErrorMessage }</span>
				</Notice>
			) }
			<TextControl
				className="card-readers-business-email-input"
				label={ __( 'Support email', 'woocommerce-payments' ) }
				value={ accountBusinessSupportEmail }
				onChange={ setAccountBusinessSupportEmail }
				type="email"
				onBlur={ updateSaveButtonAvailability }
			/>
			{ businessSupportPhoneErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ businessSupportPhoneErrorMessage }</span>
				</Notice>
			) }
			<div className="card-readers-business-phone-input">
				<span>
					{ __( 'Support phone number', 'woocommerce-payments' ) }
				</span>
				<PhoneNumberInput
					onValueChange={ setAccountBusinessSupportPhone }
					value={ accountBusinessSupportPhone }
					onValidationChange={ ( valid ) => {
						setPhoneValidity( valid );
						updateSaveButtonAvailability();
					} }
					inputProps={ {
						label: __(
							'Support phone number',
							'woocommerce-payments'
						),
						ariaLabell: __(
							'Support phone number',
							'woocommerce-payments'
						),
						name: '',
					} }
				/>
			</div>
		</>
	);
};

export default ContactDetailsSection;
