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

const SettingsSection = ( { title, description, children } ) => (
	<div className="settings-manager__section">
		<div className="settings-manager__section-details">
			<h2>{ title }</h2>
			<p>{ description }</p>
		</div>
		<div className="settings-manager__controls">{ children }</div>
	</div>
);

const SettingsManager = ( {
	enabledPaymentMethodIds: initialEnabledPaymentMethodIds,
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
		</div>
	);
};

export default SettingsManager;
