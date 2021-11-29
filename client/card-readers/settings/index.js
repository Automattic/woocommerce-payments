/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from 'wcpay/settings/settings-section';
import SettingsLayout from 'wcpay/settings/settings-layout';
import LoadableSettingsSection from 'wcpay/settings/loadable-settings-section';
import SaveSettingsSection from 'wcpay/settings/save-settings-section';
import BusinessDetailsSection from './sections/business-details';
import ContactsDetailsSection from './sections/contacts-details';
import AddressDetailsSection from './sections/address-details';
import BrandingDetailsSection from './sections/branding-details';

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

const ReceiptSettings = () => {
	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection Description={ ReadersSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<Card className="card-readers-settings__wrapper">
						<CardBody>
							<BusinessDetailsSection />
							<ContactsDetailsSection />
							<AddressDetailsSection />
							<BrandingDetailsSection />
						</CardBody>
					</Card>
				</LoadableSettingsSection>
			</SettingsSection>
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default ReceiptSettings;
