/** @format **/

/**
 * External dependencies
 */
import { TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React, { useState } from 'react';

/**
 * Internal dependencies
 */
import { useAccountCommunicationsEmail, useGetSavingError } from 'wcpay/data';

/**
 * Validates an email address format.
 *
 * @param email The email address to validate.
 * @return Whether the email is valid.
 */
const isValidEmail = ( email: string ): boolean => {
	if ( ! email ) {
		return false;
	}
	// Basic email validation regex
	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	return emailRegex.test( email );
};

const NotificationsEmailInput: React.FC = () => {
	const [
		accountCommunicationsEmail,
		setAccountCommunicationsEmail,
	] = useAccountCommunicationsEmail();

	const [ hasBlurred, setHasBlurred ] = useState( false );

	const savingError = useGetSavingError();
	const serverError =
		savingError?.data?.details?.account_communications_email?.message;

	// Only show client-side validation error if user has interacted with the field
	const showClientValidationError =
		hasBlurred &&
		accountCommunicationsEmail !== '' &&
		! isValidEmail( accountCommunicationsEmail );

	const clientValidationError = showClientValidationError
		? __( 'Please enter a valid email address.', 'woocommerce-payments' )
		: null;

	// Server error takes precedence over client validation error
	const errorMessage = serverError || clientValidationError;

	return (
		<>
			<h4>{ __( 'Notifications email', 'woocommerce-payments' ) }</h4>
			<p className="settings__notifications-email-description">
				{ __(
					'Provide an email address where you would like to receive communications about your WooPayments account.',
					'woocommerce-payments'
				) }
			</p>

			{ errorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ errorMessage }</span>
				</Notice>
			) }

			<TextControl
				className="settings__notifications-email-input"
				label={ __( 'Email address', 'woocommerce-payments' ) }
				value={ accountCommunicationsEmail }
				onChange={ setAccountCommunicationsEmail }
				onBlur={ () => setHasBlurred( true ) }
				data-testid={ 'notifications-email-input' }
				type="email"
				required
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		</>
	);
};

export default NotificationsEmailInput;
