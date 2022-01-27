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

const ContactDetailsSection = () => {
	const [
		accountBusinessSupportEmail,
		setAccountBusinessSupportEmail,
	] = useAccountBusinessSupportEmail();

	const [
		accountBusinessSupportPhone,
		setAccountBusinessSupportPhone,
	] = useAccountBusinessSupportPhone();

	const businessSuppotEmailErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_email?.message;

	const businessSuppotPhoneErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	return (
		<>
			<h4>
				{ __( 'Customer support contacts', 'woocommerce-payments' ) }
			</h4>
			{ businessSuppotEmailErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ businessSuppotEmailErrorMessage }</span>
				</Notice>
			) }
			<TextControl
				className="card-readers-business-email-input"
				label={ __( 'Support email', 'woocommerce-payments' ) }
				value={ accountBusinessSupportEmail }
				onChange={ setAccountBusinessSupportEmail }
				type="email"
			/>
			{ businessSuppotPhoneErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ businessSuppotPhoneErrorMessage }</span>
				</Notice>
			) }
			<TextControl
				className="card-readers-business-phone-input"
				label={ __( 'Support phone number', 'woocommerce-payments' ) }
				value={ accountBusinessSupportPhone }
				onChange={ setAccountBusinessSupportPhone }
				type="tel"
			/>
		</>
	);
};

export default ContactDetailsSection;
