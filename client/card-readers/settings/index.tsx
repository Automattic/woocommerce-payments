/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { Link } from '@woocommerce/components';
import { useState } from '@wordpress/element';

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
import { getAdminUrl } from 'wcpay/utils';

const isBrandingEnabled = false;

const ReadersSettingsDescription = (): JSX.Element => (
	<>
		<h2>{ __( 'Card reader receipts', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'These details will appear on printed or emailed receipts for customers that pay in person using card readers. ' +
					'Updating the details here will not affect any other stores settings.',
				'woocommerce-payments'
			) }
		</p>
		<Link
			href={ getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/card-readers/preview-receipt',
			} ) }
		>
			{ __( 'Preview a printed receipt', 'woocommerce-payments' ) }
		</Link>
	</>
);

const ReceiptSettings = (): JSX.Element => {
	const [ isSaveDisabled, setDisabled ] = useState( false );

	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection description={ ReadersSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<Card className="card-readers-settings__wrapper">
						<CardBody>
							<BusinessDetailsSection
								isDisabled={ isSaveDisabled }
								setDisabled={ setDisabled }
							/>
							<ContactsDetailsSection />
							<AddressDetailsSection />
							{ isBrandingEnabled && <BrandingDetailsSection /> }
						</CardBody>
					</Card>
				</LoadableSettingsSection>
			</SettingsSection>
			<SaveSettingsSection disabled={ isSaveDisabled } />
		</SettingsLayout>
	);
};

export default ReceiptSettings;
