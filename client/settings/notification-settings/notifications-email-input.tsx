/** @format **/

/**
 * External dependencies
 */
import { TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import { useCommunicationsEmail, useGetSavingError } from 'wcpay/data';

const NotificationsEmailInput: React.FC = () => {
	const [
		communicationsEmail,
		setCommunicationsEmail,
	] = useCommunicationsEmail();

	const savingError = useGetSavingError();
	const communicationsEmailError =
		savingError?.data?.details?.communications_email?.message;

	return (
		<>
			{ communicationsEmailError && (
				<Notice status="error" isDismissible={ false }>
					<span>{ communicationsEmailError }</span>
				</Notice>
			) }

			<TextControl
				className="settings__notifications-email-input"
				help={ __(
					'Email address used for WooPayments communications.',
					'woocommerce-payments'
				) }
				label={ __( 'Communications email', 'woocommerce-payments' ) }
				value={ communicationsEmail }
				onChange={ setCommunicationsEmail }
				data-testid={ 'notifications-email-input' }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		</>
	);
};

export default NotificationsEmailInput;
