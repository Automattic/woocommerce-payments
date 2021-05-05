/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React, { useState } from 'react';
import { Card, CardBody, ExternalLink } from '@wordpress/components';
/**
 * Internal dependencies
 */
import WCPayLogo from '../../wcpay-logo.js';
import PaymentMethods from '../../payment-methods';
import DigitalWallets from '../digital-wallets';
import SettingsSection from '../settings-section';
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
		<h2>{ __( 'Settings', 'woocommerce-payments' ) }</h2>
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

const SettingsManager = ( {
	enabledPaymentMethodIds: initialEnabledPaymentMethodIds,
	accountStatus = {},
} ) => {
	const [ enabledPaymentMethodIds, setEnabledPaymentMethodIds ] = useState(
		initialEnabledPaymentMethodIds
	);

	return (
		<>
			<Card size="large" className="settings-manager__banner">
				<CardBody>
					<WCPayLogo width="257" height="72" />
				</CardBody>
			</Card>
			<div className="settings-manager">
				<SettingsSection Description={ PaymentMethodsDescription }>
					<PaymentMethods
						enabledMethodIds={ enabledPaymentMethodIds }
						onEnabledMethodIdsChange={ setEnabledPaymentMethodIds }
					/>
				</SettingsSection>
				<SettingsSection Description={ DigitalWalletsDescription }>
					<DigitalWallets />
				</SettingsSection>
				<SettingsSection Description={ GeneralSettingsDescription }>
					<GeneralSettings
						accountLink={ accountStatus.accountLink }
					/>
				</SettingsSection>
				<SettingsSection>
					<TestModeSettings />
				</SettingsSection>
			</div>
		</>
	);
};

export default SettingsManager;
