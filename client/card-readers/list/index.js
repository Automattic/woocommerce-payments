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
import SettingsSection from 'wcpay/settings//settings-section';
import SettingsLayout from 'wcpay/settings//settings-layout';
import LoadableSettingsSection from 'wcpay/settings//loadable-settings-section';

const ReadersListDescription = () => (
	<>
		<h2>{ __( 'Connected card readers', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Card readers are marked as active if they’ve processed one or more transactions durring the current billing cycle. ' +
					'To connect or disconnect card readers, use the WooCommerce Payments mobile application.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const ReadersList = () => {
	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection Description={ ReadersListDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<Card className="card-readers-list__wrapper">
						<CardBody>List</CardBody>
					</Card>
				</LoadableSettingsSection>
			</SettingsSection>
		</SettingsLayout>
	);
};

export default ReadersList;
