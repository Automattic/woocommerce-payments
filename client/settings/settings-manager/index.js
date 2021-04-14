/** @format */
/**
 * External dependencies
 */
import { useState } from 'react';

/**
 * Internal dependencies
 */
import PaymentMethods from '../../payment-methods';

const SettingsManager = ( {
	enabledPaymentMethodIds: initialEnabledPaymentMethodIds,
} ) => {
	const [ enabledPaymentMethodIds, setEnabledPaymentMethodIds ] = useState(
		initialEnabledPaymentMethodIds
	);

	return (
		<div>
			<PaymentMethods
				enabledMethodIds={ enabledPaymentMethodIds }
				onEnabledMethodIdsChange={ setEnabledPaymentMethodIds }
			/>
		</div>
	);
};

export default SettingsManager;
