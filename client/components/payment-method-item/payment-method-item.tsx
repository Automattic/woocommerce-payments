/**
 * External dependencies
 */
import clsx from 'clsx';
import React from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemProps } from './types';
import PaymentMethodItemCheckbox from './checkbox';
import PaymentMethodItemBody from './body';
import './style.scss';

const PaymentMethodItem: React.FC< PaymentMethodItemProps > = ( {
	id,
	className,
	children,
} ) => {
	const rowChildren: React.ReactNode[] = [];
	const trailingChildren: React.ReactNode[] = [];

	React.Children.forEach( children, ( child ) => {
		if (
			React.isValidElement( child ) &&
			( child.type === PaymentMethodItemCheckbox ||
				child.type === PaymentMethodItemBody )
		) {
			rowChildren.push( child );
		} else {
			trailingChildren.push( child );
		}
	} );

	return (
		<li className={ clsx( 'payment-method-item', className ) } id={ id }>
			<div className="payment-method-item__row">{ rowChildren }</div>
			{ trailingChildren }
		</li>
	);
};

PaymentMethodItem.displayName = 'PaymentMethodItem';

export default PaymentMethodItem;
