/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from 'react';

/**
 * Internal dependencies
 */
import PaymentMethods from '../../payment-methods';
import DigitalWallets from '../digital-wallets';
import SettingsSection from '../settings-section';
import GeneralSettings from '../general-settings';
import TestModeSettings from '../test-mode-settings';

const SettingsManager = ( {
	enabledPaymentMethodIds: initialEnabledPaymentMethodIds,
	accountStatus = {},
} ) => {
	const [ enabledPaymentMethodIds, setEnabledPaymentMethodIds ] = useState(
		initialEnabledPaymentMethodIds
	);

	return (
		<div className="settings-manager">
			<SettingsSection
				title={ __(
					'Payments accepted on checkout',
					'woocommerce-payments'
				) }
				description={ __(
					'Add and edit payments available to customers at checkout. Drag & drop to reorder.',
					'woocommerce-payments'
				) }
			>
				<PaymentMethods
					enabledMethodIds={ enabledPaymentMethodIds }
					onEnabledMethodIdsChange={ setEnabledPaymentMethodIds }
				/>
			</SettingsSection>
			<SettingsSection
				title={ __(
					'Digital wallets & express payment methods',
					'woocommerce-payments'
				) }
				description={ __(
					// eslint-disable-next-line max-len
					'Let customers use express payment methods and digital wallets like Apple Pay and Google Pay for fast & easy checkouts.',
					'woocommerce-payments'
				) }
			>
				<DigitalWallets />
			</SettingsSection>
			<SettingsSection
				title={ __( 'Settings', 'woocommerce-payments' ) }
				description={ __(
					"Change WooCommerce Payments settings and update your store's configuration to ensure smooth transactions.",
					'woocommerce-payments'
				) }
			>
				<GeneralSettings accountLink={ accountStatus.accountLink } />
			</SettingsSection>
			<SettingsSection>
				<TestModeSettings />
			</SettingsSection>
		</div>
	);
};

export default SettingsManager;
