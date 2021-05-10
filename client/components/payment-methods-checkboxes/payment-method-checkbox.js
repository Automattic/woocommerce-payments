/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl, Icon } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '../../settings/payment-method-icon';
import './payment-method-checkbox.scss';

const PaymentMethodCheckbox = ( { onChange, name, checked, fees } ) => {
	const handleChange = useCallback(
		( enabled ) => {
			onChange( name, enabled );
		},
		[ name, onChange ]
	);

	const label = useMemo( () => <PaymentMethodIcon name={ name } showName />, [
		name,
	] );

	return (
		<li className="payment-method-checkbox">
			<CheckboxControl
				checked={ checked }
				onChange={ handleChange }
				label={ label }
			/>
			<span className="payment-method-checkbox__fees">{ fees }</span>
			<Icon
				className="payment-method-checkbox__info"
				icon="info-outline"
			/>
		</li>
	);
};

export default PaymentMethodCheckbox;
