/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '../../settings/payment-method-icon';
import Pill from '../pill';
import Tooltip from '../tooltip/tooltip-hover';
import './payment-method-checkbox.scss';

const infoMap = {
	card: __(
		'Offer checkout with major credit and debit cards without leaving your store.',
		'woocommerce-payments'
	),
	giropay: __(
		'Expand your business with giropay — Germany’s second most popular payment system.',
		'woocommerce-payments'
	),
	sepa_debit: __(
		'Reach 500 million customers and over 20 million businesses across the European Union.',
		'woocommerce-payments'
	),
	sofort: __(
		'Accept secure bank transfers from Austria, Belgium, Germany, Italy, and Netherlands.',
		'woocommerce-payments'
	),
};

const PaymentMethodInfo = ( { name } ) => {
	const info = infoMap[ name ];
	if ( ! info ) return null;

	return (
		<Tooltip content={ info }>
			<div className="payment-method-checkbox__info">
				<VisuallyHidden>{ info }</VisuallyHidden>
				<Icon icon="info-outline" />
			</div>
		</Tooltip>
	);
};

const PaymentMethodCheckbox = ( { onChange, name, checked = false, fees } ) => {
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
			<Tooltip content="Base transaction fees">
				<Pill className="payment-method-checkbox__fees">
					<VisuallyHidden>
						{ sprintf(
							__(
								'Base transaction fees: %s',
								'woocommerce-payments'
							),
							fees
						) }
					</VisuallyHidden>
					<span aria-hidden="true">{ fees }</span>
				</Pill>
			</Tooltip>
			<PaymentMethodInfo name={ name } />
		</li>
	);
};

export default PaymentMethodCheckbox;
