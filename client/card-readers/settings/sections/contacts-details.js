/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, Notice } from '@wordpress/components';

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

	if ( '' === accountBusinessSupportEmail ) {
		businessSupportEmailErrorMessage = __(
			'Support email cannot be empty!',
			'woocommerce-payments'
		);
	}

	if ( '' === accountBusinessSupportPhone ) {
		businessSupportPhoneErrorMessage = __(
			'Support phone number cannot be empty!',
			'woocommerce-payments'
		);
	}

	const updateSaveButtonAvailability = () => {
		setSaveDisabled(
			'' === accountBusinessSupportEmail ||
				'' === accountBusinessSupportPhone
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
			<TextControl
				className="card-readers-business-phone-input"
				label={ __( 'Support phone number', 'woocommerce-payments' ) }
				value={ accountBusinessSupportPhone }
				onChange={ setAccountBusinessSupportPhone }
				type="tel"
				onBlur={ updateSaveButtonAvailability }
			/>
		</>
	);
};

export default ContactDetailsSection;
