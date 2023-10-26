/** @format */
/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';
import paymentMethodsMap from '../../payment-methods-map';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethod = paymentMethodsMap[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { label, icon: Icon } = paymentMethod;

	return (
		<span
			className={ classNames(
				'woocommerce-payments__payment-method-icon'
			) }
		>
			<Icon />
			{ showName && (
				<span className="woocommerce-payments__payment-method-icon__label">
					{ label }
				</span>
			) }
		</span>
	);
};

export default PaymentMethodIcon;
