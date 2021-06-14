/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';
import SettingsSection from '../settings-section';
import { getPaymentSettingsUrl } from '../../utils';
import DigitalWalletsSettings from './digital-wallets-settings';
import SettingsLayout from '../settings-layout';
import { useSettings } from '../../data';
import { LoadableBlock } from '../../components/loadable';
import SaveSettingsSection from '../save-settings-section';

/* eslint-disable camelcase */
const methods = {
	digital_wallets: {
		title: 'Express checkouts',
		description: () => (
			<>
				<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
				<p>
					{ __(
						'Decide how buttons for digital wallets like Apple Pay and Google Pay are displayed in your store.',
						'woocommerce-payments'
					) }
				</p>
				<p>
					<ExternalLink href="https://developer.apple.com/design/human-interface-guidelines/apple-pay/overview/introduction/">
						{ __(
							'View Apple Pay Guidelines',
							'woocommerce-payments'
						) }
					</ExternalLink>
				</p>
				<p>
					<ExternalLink href="https://developers.google.com/pay/api/web/guides/brand-guidelines">
						{ __(
							'View Google Pay Guidelines',
							'woocommerce-payments'
						) }
					</ExternalLink>
				</p>
			</>
		),
		controls: () => <DigitalWalletsSettings />,
	},
};
/* eslint-enable camelcase */

const PaymentMethodSettings = ( { methodId } ) => {
	const method = methods[ methodId ];
	const { isLoading } = useSettings();

	if ( ! method ) {
		return (
			<p>
				{ __(
					'Invalid payment method ID specified.',
					'woocommerce-payments'
				) }
			</p>
		);
	}

	const { title, description: Description, controls: Controls } = method;

	return (
		<SettingsLayout>
			<h2 className="payment-method-settings__breadcrumbs">
				<a href={ getPaymentSettingsUrl() }>
					{ __( 'WooCommerce Payments', 'woocommerce-payments' ) }
				</a>{ ' ' }
				&gt; <span>{ title }</span>
			</h2>

			<SettingsSection Description={ Description }>
				<LoadableBlock isLoading={ isLoading } numLines={ 30 }>
					<Controls />
				</LoadableBlock>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default PaymentMethodSettings;
