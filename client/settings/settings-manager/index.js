/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from 'react';

/**
 * Internal dependencies
 */
import { useSettings } from 'data';
import PaymentMethods from '../../payment-methods';
import General from './general';
import { Button, CardFooter } from '@wordpress/components';

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
	const { settings = {}, isLoading, updateSettings } = useSettings();

	const handleSubmit = () => {
		updateSettings();
	};

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
				title={ __( 'General Settings', 'woocommerce-payments' ) }
				description={ __(
					'Change WooCommerce Payments settings and update your store’s configuration to ensure smooth transactions.',
					'woocommerce-payments'
				) }
			>
				<General settings={ settings } />
			</SettingsSection>
			<Button isPrimary isLarge onClick={ handleSubmit }>
				{ __( 'Save changes', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

export default SettingsManager;
