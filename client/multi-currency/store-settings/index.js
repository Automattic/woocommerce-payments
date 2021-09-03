/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../../settings/settings-section';
import SettingsLayout from '../../settings/settings-layout';
import './style.scss';

const StoreSettingsDescription = () => {
	const LEARN_MORE_URL =
		'https://docs.woocommerce.com/document/payments/currencies/multi-currency-setup/';

	return (
		<>
			<h2>{ __( 'Store settings', 'woocommerce-payments' ) }</h2>
			<p>
				{ createInterpolateElement(
					sprintf(
						__(
							// eslint-disable-next-line max-len
							'Store settings allow your customers to choose which currency they would like to use when shopping at your store. <a>Learn more</a>',
							'woocommerce-payments'
						),
						LEARN_MORE_URL
					),
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					{ a: <a href={ LEARN_MORE_URL } /> }
				) }
			</p>
		</>
	);
};

const StoreSettings = () => {
	return (
		<SettingsLayout displayBanner={ false }>
			<SettingsSection
				Description={ StoreSettingsDescription }
				className="store-settings-section"
			>
				<Card>
					<CardBody className="store-settings__card-body" />
				</Card>
			</SettingsSection>
		</SettingsLayout>
	);
};

export default StoreSettings;
