/** @format */
/**
 * External dependencies
 */
import React, { ReactNode } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

const PaymentMethodsList = ( {
	className,
	children,
}: {
	className: string;
	children: ReactNode;
} ): React.ReactElement => {
	return (
		<ul className={ classNames( 'payment-methods-list', className ) }>
			{ children }
		</ul>
	);
};

export default PaymentMethodsList;
