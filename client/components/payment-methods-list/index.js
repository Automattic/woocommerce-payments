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

const PaymentMethodsList = ( { className, children } ) => {
	return (
		<ul className={ classNames( 'payment-methods-list', className ) }>
			{ children }
		</ul>
	);
};

export default PaymentMethodsList;
