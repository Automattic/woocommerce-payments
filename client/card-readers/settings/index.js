/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from 'wcpay/settings/settings-section';
import SettingsLayout from 'wcpay/settings/settings-layout';
import LoadableSettingsSection from 'wcpay/settings/loadable-settings-section';
import { useAccountBusinessName, useAccountBusinessURL } from '../../data';
import SaveSettingsSection from 'wcpay/settings/save-settings-section';

const ReadersSettingsDescription = () => (
	<>
		<h2>{ __( 'Card reader receipts', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'These details will appear on printed or emailed receipts for customers that pay in person using card readers. ' +
					'Updating the details here will not affect any other stores settings.',
				'woocommerce-payments'
			) }
		</p>
		<p>
			<a href="#preview-print">
				{ __( 'Preview a printed receipt', 'woocommerce-payments' ) }
			</a>
		</p>
		<p>
			<a href="#preview-email">
				{ __( 'Preview an emailed receipt', 'woocommerce-payments' ) }
			</a>
		</p>
	</>
);

const ReadersSettings = () => {
	const [
		accountBusinessName,
		setAccountBusinessName,
	] = useAccountBusinessName();

	const [
		accountBusinessURL,
		setAccountBusinessURL,
	] = useAccountBusinessURL();

	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection Description={ ReadersSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<Card className="card-readers-settings__wrapper">
						<CardBody>
							<h4>
								{ __(
									'Business details',
									'woocommerce-payments'
								) }
							</h4>
							<TextControl
								className="card-readers-business-name-input"
								label={ __(
									'Business name',
									'woocommerce-payments'
								) }
								value={ accountBusinessName }
								onChange={ setAccountBusinessName }
							/>
							<TextControl
								className="card-readers-business-name-input"
								label={ __(
									'Business URL',
									'woocommerce-payments'
								) }
								value={ accountBusinessURL }
								onChange={ setAccountBusinessURL }
							/>
						</CardBody>
					</Card>
				</LoadableSettingsSection>
			</SettingsSection>
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default ReadersSettings;
