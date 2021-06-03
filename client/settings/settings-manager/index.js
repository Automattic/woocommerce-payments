/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useSettings } from 'data';
import { LoadableBlock } from '../../components/loadable';
import AdvancedSettings from '../advanced-settings';
import PaymentMethods from '../../payment-methods';
import DigitalWallets from '../digital-wallets';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
import TestModeSettings from '../test-mode-settings';
import ApplePayIcon from '../../gateway-icons/apple-pay';
import GooglePayIcon from '../../gateway-icons/google-pay';
import SettingsLayout from '../settings-layout';
import SaveSettingsSection from '../save-settings-section';
import WCPaySettingsContext from '../wcpay-settings-context';

const PaymentMethodsDescription = () => (
	<>
		<h2>
			{ __( 'Payments accepted on checkout', 'woocommerce-payments' ) }
		</h2>
		<p>
			{ __(
				'Add and edit payments available to customers at checkout. ' +
					'Based on their device type, location, and purchase history, ' +
					'your customers will only see the most relevant payment methods.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const DigitalWalletsDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
		<ul className="settings-section__icons">
			<li>
				<ApplePayIcon />
			</li>
			<li>
				<GooglePayIcon />
			</li>
		</ul>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets ' +
					'for faster, more secure checkouts across different parts of your store.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://docs.woocommerce.com/document/payments/apple-pay/">
			{ __( 'How it works?', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const GeneralSettingsDescription = () => (
	<>
		<h2>{ __( 'General', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Enable or disable WooCommerce Payments on your store and turn on test mode to simulate transactions.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://docs.woocommerce.com/document/payments/faq/">
			{ __( 'View Frequently Asked Questions', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const SettingsManager = () => {
	const { isLoading } = useSettings();
	const {
		accountStatus: { accountLink },
	} = useContext( WCPaySettingsContext );

	return (
		<SettingsLayout>
			<SettingsSection Description={ PaymentMethodsDescription }>
				<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
					<PaymentMethods />
				</LoadableBlock>
			</SettingsSection>
			<SettingsSection Description={ DigitalWalletsDescription }>
				<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
					<DigitalWallets />
				</LoadableBlock>
			</SettingsSection>
			<SettingsSection Description={ GeneralSettingsDescription }>
				<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
					<GeneralSettings accountLink={ accountLink } />
				</LoadableBlock>
			</SettingsSection>
			<SettingsSection>
				<LoadableBlock isLoading={ isLoading } numLines={ 10 }>
					<TestModeSettings />
				</LoadableBlock>
			</SettingsSection>
			<AdvancedSettings />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
