/** @format */
/**
 * External dependencies
 */
import React from 'react';

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

	const { label, Icon } = paymentMethod;

	return (
		<span className="woocommerce-payments__payment-method-icon">
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
