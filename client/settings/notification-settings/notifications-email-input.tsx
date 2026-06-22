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
import {
	useAccountCommunicationsEmail,
	useGetSavingError,
	useSettings,
} from 'wcpay/data/settings';
import { isEmail } from 'wcpay/utils/email-validation';

interface NotificationsEmailInputProps {
	onValidationChange?: ( isValid: boolean ) => void;
}

const NotificationsEmailInput: React.FC< NotificationsEmailInputProps > = ( {
	onValidationChange,
} ) => {
	const [ accountCommunicationsEmail, setAccountCommunicationsEmail ] =
		useAccountCommunicationsEmail();
	const { isLoading } = useSettings();

	const [ hasBlurred, setHasBlurred ] = useState( false );
	const [ confirmEmail, setConfirmEmail ] = useState( '' );
	const [ hasConfirmBlurred, setHasConfirmBlurred ] = useState( false );
	const [ initialEmail, setInitialEmail ] = useState< string | null >( null );

	// Capture the initial email value once settings have loaded from the server.
	useEffect( () => {
		if ( ! isLoading && initialEmail === null ) {
			setInitialEmail( accountCommunicationsEmail ?? '' );
		}
	}, [ isLoading, accountCommunicationsEmail, initialEmail ] );

	const emailHasChanged =
		initialEmail !== null && accountCommunicationsEmail !== initialEmail;

	const savingError = useGetSavingError();
	const serverError =
		savingError?.data?.details?.account_communications_email?.message;

	// Only show client-side validation error if user has interacted with the field
	// and the value is non-empty. An empty value is handled by server-side validation.
	const showClientValidationError =
		hasBlurred &&
		accountCommunicationsEmail !== '' &&
		! isEmail( accountCommunicationsEmail );

	const clientValidationError = showClientValidationError
		? __( 'Please enter a valid email address.', 'woocommerce-payments' )
		: null;

	// Server error takes precedence over client validation error
	const errorMessage = serverError || clientValidationError;
	const errorId = 'notifications-email-error';

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

	// Treat empty as valid client-side; the server enforces the required rule
	// so merchants who have never set a notifications email don't get Save
	// disabled on mount without any interaction.
	const isValid =
		emailsMatch &&
		( accountCommunicationsEmail === '' ||
			isEmail( accountCommunicationsEmail ) );

	// Notify parent of validation state changes.
	useEffect( () => {
		onValidationChange?.( isValid );
	}, [ isValid, onValidationChange ] );

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

			<div
				id={ errorId }
				role="status"
				data-testid="notifications-email-error"
			>
				{ errorMessage && (
					<Notice status="error" isDismissible={ false }>
						<span>{ errorMessage }</span>
					</Notice>
				) }
			</div>

			<TextControl
				className="settings__notifications-email-input"
				label={ __( 'Email address', 'woocommerce-payments' ) }
				value={ accountCommunicationsEmail }
				onChange={ setAccountCommunicationsEmail }
				onBlur={ () => setHasBlurred( true ) }
				id="account-communications-email-input"
				data-testid={ 'notifications-email-input' }
				type="email"
				required
				aria-invalid={ errorMessage ? true : undefined }
				aria-describedby={ errorMessage ? errorId : undefined }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<div
				id="notifications-email-mismatch-error"
				role="status"
				data-testid="notifications-email-mismatch-error"
			>
				{ mismatchError && (
					<Notice status="error" isDismissible={ false }>
						<span>{ mismatchError }</span>
					</Notice>
				) }
			</div>

			{ emailHasChanged && (
				<>
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
						aria-invalid={ mismatchError ? true : undefined }
						aria-describedby={
							mismatchError
								? 'notifications-email-mismatch-error'
								: undefined
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</>
			) }
		</>
	);
};

export default NotificationsEmailInput;
