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
import SettingsSection from '../settings-section';

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
		</div>
	);
};

export default SettingsManager;
