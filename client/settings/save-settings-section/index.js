/** @format */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useSettings } from '../../data';
import wcpayTracks from '../../tracks';
import SettingsSection from '../settings-section';
import './style.scss';

const SaveSettingsSection = () => {
	const { saveSettings, isSaving, isLoading, settings } = useSettings();

	// Keep the inital value of is_payment_request_enabled
	// in state for recording its track on change.
	const [
		initialIsPaymentRequestEnabled,
		setInitialIsPaymentRequestEnabled,
	] = useState( null );

	if (
		null === initialIsPaymentRequestEnabled &&
		settings &&
		'undefined' !== typeof settings.is_payment_request_enabled
	) {
		setInitialIsPaymentRequestEnabled(
			settings.is_payment_request_enabled
		);
	}

	const saveOnClick = async () => {
		const isSuccess = await saveSettings();

		// Track the event when the value changed and the
		// settings were successfully saved.
		if (
			isSuccess &&
			initialIsPaymentRequestEnabled !==
				settings.is_payment_request_enabled
		) {
			wcpayTracks.recordEvent(
				wcpayTracks.events.PAYMENT_REQUEST_SETTINGS_CHANGE,
				{
					enabled: settings.is_payment_request_enabled ? 'yes' : 'no',
				}
			);

			// Update the "initial" value to properly track consecutive saves.
			setInitialIsPaymentRequestEnabled(
				settings.is_payment_request_enabled
			);
		}
	};

	return (
		<SettingsSection className="save-settings-section">
			<Button
				isPrimary
				isBusy={ isSaving }
				disabled={ isSaving || isLoading }
				onClick={ saveOnClick }
			>
				{ __( 'Save changes', 'woocommerce-payments' ) }
			</Button>
		</SettingsSection>
	);
};

export default SaveSettingsSection;
