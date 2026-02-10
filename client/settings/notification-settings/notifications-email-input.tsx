/** @format **/

/**
 * External dependencies
 */
import { TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React, { useState, useEffect } from 'react';

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

interface NotificationsEmailInputProps {
	onValidationChange?: ( isValid: boolean ) => void;
}

const NotificationsEmailInput: React.FC< NotificationsEmailInputProps > = ( {
	onValidationChange,
} ) => {
	const [
		accountCommunicationsEmail,
		setAccountCommunicationsEmail,
	] = useAccountCommunicationsEmail();

	const [ hasBlurred, setHasBlurred ] = useState( false );
	const [ confirmEmail, setConfirmEmail ] = useState( '' );
	const [ hasConfirmBlurred, setHasConfirmBlurred ] = useState( false );
	const [ initialEmail, setInitialEmail ] = useState< string | null >( null );

	// Capture the initial email value once it loads from the server.
	useEffect( () => {
		if ( accountCommunicationsEmail && initialEmail === null ) {
			setInitialEmail( accountCommunicationsEmail );
		}
	}, [ accountCommunicationsEmail, initialEmail ] );

	const emailHasChanged =
		initialEmail !== null && accountCommunicationsEmail !== initialEmail;

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

	const emailsMatch =
		! emailHasChanged || accountCommunicationsEmail === confirmEmail;
	const showMismatchError =
		emailHasChanged && hasConfirmBlurred && ! emailsMatch;

	const mismatchError = showMismatchError
		? __(
				'Email addresses do not match. Please re-enter your email address.',
				'woocommerce-payments'
		  )
		: null;

	// Notify parent of validation state changes.
	useEffect( () => {
		if ( onValidationChange ) {
			onValidationChange( emailsMatch );
		}
	}, [ emailsMatch, onValidationChange ] );

	return (
		<>
			<h4>{ __( 'Notifications email', 'woocommerce-payments' ) }</h4>
			<p className="settings__notifications-email-description">
				{ __(
					'Provide an email address where you would like to receive communications about your WooPayments account.',
					'woocommerce-payments'
				) }
			</p>

			<Notice
				className="settings__notifications-email-warning"
				status="warning"
				isDismissible={ false }
			>
				<span>
					{ __(
						'Anyone with access to this email address will be treated as the account owner. Please verify the address carefully.',
						'woocommerce-payments'
					) }
				</span>
			</Notice>

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

			{ emailHasChanged && (
				<>
					{ mismatchError && (
						<Notice status="error" isDismissible={ false }>
							<span>{ mismatchError }</span>
						</Notice>
					) }

					<TextControl
						className="settings__notifications-email-confirm-input"
						label={ __(
							'Confirm email address',
							'woocommerce-payments'
						) }
						value={ confirmEmail }
						onChange={ setConfirmEmail }
						onBlur={ () => setHasConfirmBlurred( true ) }
						data-testid={ 'notifications-email-confirm-input' }
						type="email"
						required
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</>
			) }
		</>
	);
};

export default NotificationsEmailInput;
