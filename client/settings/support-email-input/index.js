/**
 * External dependencies
 */
import { TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useAccountBusinessSupportEmail, useGetSavingError } from 'wcpay/data';
import { isEmail } from 'wcpay/utils/email-validation';
import { useEffect, useRef, useState } from 'react';

const SupportEmailInput = ( { setInputValid } ) => {
	const [ supportEmail, setSupportEmail ] = useAccountBusinessSupportEmail();
	const [ hasBlurred, setHasBlurred ] = useState( false );

	let supportEmailError =
		useGetSavingError()?.data?.details?.account_business_support_email
			?.message;

	const currentEmail = useRef( supportEmail ).current;
	if ( supportEmail === '' && currentEmail !== '' ) {
		supportEmailError = __(
			'Support email cannot be empty once it has been set before, please specify.',
			'woocommerce-payments'
		);
	}

	const hasInvalidFormat = supportEmail !== '' && ! isEmail( supportEmail );

	const clientValidationError =
		hasBlurred && hasInvalidFormat
			? __(
					'Please enter a valid email address.',
					'woocommerce-payments'
			  )
			: null;

	// Server error takes precedence over client validation error
	const errorMessage = supportEmailError || clientValidationError;
	const errorId = 'support-email-error';

	useEffect( () => {
		if ( setInputValid ) {
			setInputValid( ! supportEmailError && ! hasInvalidFormat );
		}
	}, [ supportEmailError, setInputValid, hasInvalidFormat ] );

	return (
		<>
			<div id={ errorId } role="status" data-testid="support-email-error">
				{ errorMessage && (
					<Notice status="error" isDismissible={ false }>
						<span>{ errorMessage }</span>
					</Notice>
				) }
			</div>

			<TextControl
				className="settings__account-business-support-email-input"
				help={ __(
					'This may be visible on receipts, invoices, and automated emails from your store.',
					'woocommerce-payments'
				) }
				label={ __( 'Support email', 'woocommerce-payments' ) }
				value={ supportEmail }
				onChange={ setSupportEmail }
				onBlur={ () => setHasBlurred( true ) }
				data-testid={ 'account-business-support-email-input' }
				type="email"
				aria-invalid={ errorMessage ? true : undefined }
				aria-describedby={ errorMessage ? errorId : undefined }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		</>
	);
};

export default SupportEmailInput;
