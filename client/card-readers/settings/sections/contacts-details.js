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

	let businessSuppotEmailErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_email?.message;

	let businessSuppotPhoneErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	if ( '' === accountBusinessSupportEmail ) {
		businessSuppotEmailErrorMessage = __(
			'Support email cannot be empty!',
			'woocommerce-payments'
		);
	}

	if ( '' === accountBusinessSupportPhone ) {
		businessSuppotPhoneErrorMessage = __(
			'Support phone number cannot be empty!',
			'woocommerce-payments'
		);
	}

	const updateSaveButtonAvailability = () => {
		if (
			'' === accountBusinessSupportEmail ||
			'' === accountBusinessSupportPhone
		) {
			setSaveDisabled( true );
		} else {
			setSaveDisabled( false );
		}
	};

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
				onBlur={ updateSaveButtonAvailability }
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
				onBlur={ updateSaveButtonAvailability }
			/>
		</>
	);
};

export default ContactDetailsSection;
