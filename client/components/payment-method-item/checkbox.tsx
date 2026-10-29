/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { CheckboxControl, FormToggle } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemCheckboxProps } from './types';
import PaymentMethodItemToggleContext from './toggle-context';

const PaymentMethodItemCheckbox: React.FC<
	PaymentMethodItemCheckboxProps
> = ( { label, checked, disabled, onChange, 'data-testid': dataTestId } ) => {
	const isToggle = useContext( PaymentMethodItemToggleContext );
	return (
		<div className="payment-method-item__checkbox">
			{ isToggle ? (
				<FormToggle
					aria-label={ label }
					checked={ checked }
					disabled={ disabled }
					onChange={ ( event ) => onChange( event.target.checked ) }
					data-testid={ dataTestId }
				/>
			) : (
				<CheckboxControl
					label={ label }
					checked={ checked }
					disabled={ disabled }
					onChange={ onChange }
					data-testid={ dataTestId }
					__nextHasNoMarginBottom
				/>
			) }
		</div>
	);
};

PaymentMethodItemCheckbox.displayName = 'PaymentMethodItemCheckbox';

export default PaymentMethodItemCheckbox;
