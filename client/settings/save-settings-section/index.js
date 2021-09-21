/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useSettings } from '../../data';
import SettingsSection from '../settings-section';
import './style.scss';

const SaveSettingsSection = () => {
	const { saveSettings, isSaving, isLoading } = useSettings();

	return (
		<SettingsSection className="save-settings-section">
			<Button
				isPrimary
				isBusy={ isSaving }
				disabled={ isSaving || isLoading }
				onClick={ saveSettings }
			>
				{ __( 'Save changes', 'woocommerce-payments' ) }
			</Button>
		</SettingsSection>
	);
};

export default SaveSettingsSection;
