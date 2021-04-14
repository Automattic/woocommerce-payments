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
	enabledMethodIds: initialEnabledPaymentMethodIds,
} ) => {
	const [ enabledPaymentMethodIds, setEnabledPaymentMethodIds ] = useState(
		initialEnabledPaymentMethodIds
	);

	return (
		<div>
			<PaymentMethods
				enabledMethodIds={ enabledPaymentMethodIds }
				onEnabledMethodsChange={ setEnabledPaymentMethodIds }
			/>
		</div>
	);
};

export default SettingsManager;
