/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemCheckboxProps } from './types';

const PaymentMethodItemCheckbox: React.FC< PaymentMethodItemCheckboxProps > = ( {
	label,
	checked,
	disabled,
	onChange,
} ) => (
	<div className="payment-method-item__checkbox">
		<CheckboxControl
			label={ label }
			checked={ checked }
			disabled={ disabled }
			onChange={ onChange }
			__nextHasNoMarginBottom
		/>
	</div>
);

PaymentMethodItemCheckbox.displayName = 'PaymentMethodItemCheckbox';

export default PaymentMethodItemCheckbox;
