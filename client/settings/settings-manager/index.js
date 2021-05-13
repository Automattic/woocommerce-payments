/** @format */
/**
 * External dependencies
 */
import { Button, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';
import { LoadableBlock } from 'components/loadable';
import { useSettings } from 'data';
import Banner from '../../banner';
import PaymentMethods from '../../payment-methods';
import SettingsSection from '../settings-section';
import DigitalWallets from 'settings/digital-wallets';
import GeneralSettings from '../general-settings';
import TestModeSettings from '../test-mode-settings';
import ApplePayIcon from '../../gateway-icons/apple-pay';
import GooglePayIcon from '../../gateway-icons/google-pay';

const PaymentMethodsDescription = () => (
	<>
		<h2>
			{ __( 'Payments accepted on checkout', 'woocommerce-payments' ) }
		</h2>
		<p>
			{ __(
				'Add and edit payments available to customers at checkout. Drag & drop to reorder.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const DigitalWalletsDescription = () => (
	<>
		<h2>
			{ __(
				'Digital wallets & express payment methods',
				'woocommerce-payments'
			) }
		</h2>
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
				'Let customers use express payment methods and digital wallets like Apple Pay and Google Pay for fast & easy checkouts.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://docs.woocommerce.com/document/payments/apple-pay/">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const GeneralSettingsDescription = () => (
	<>
		<h2>{ __( 'General settings', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				"Change WooCommerce Payments settings and update your store's configuration to ensure smooth transactions.",
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://docs.woocommerce.com/document/payments/faq/">
			{ __( 'View Frequently Asked Questions', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

const SettingsManager = ( { accountStatus = {} } ) => {
	const { saveSettings, isSaving, isLoading } = useSettings();

	return (
		<>
			<Banner />
			<div className="settings-manager">
				<SettingsSection Description={ PaymentMethodsDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
						<PaymentMethods />
					</LoadableBlock>
				</SettingsSection>
				<SettingsSection Description={ DigitalWalletsDescription }>
					<DigitalWallets />
				</SettingsSection>
				<SettingsSection Description={ GeneralSettingsDescription }>
					<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
						<GeneralSettings
							accountLink={ accountStatus.accountLink }
						/>
					</LoadableBlock>
				</SettingsSection>
				<SettingsSection>
					<TestModeSettings />
				</SettingsSection>
				<SettingsSection className="settings-manager__buttons">
					<Button
						isPrimary
						isBusy={ isSaving }
						disabled={ isSaving || isLoading }
						onClick={ saveSettings }
					>
						{ __( 'Save changes', 'woocommerce-payments' ) }
					</Button>
				</SettingsSection>
			</div>
		</>
	);
};

export default SettingsManager;
