/**
 * External dependencies
 */
import clsx from 'clsx';
import React from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemActionProps } from './types';

const PaymentMethodItemAction: React.FC< PaymentMethodItemActionProps > = ( {
	children,
	className,
} ) => (
	<div className={ clsx( 'payment-method-item__action', className ) }>
		{ children }
	</div>
);

PaymentMethodItemAction.displayName = 'PaymentMethodItemAction';

export default PaymentMethodItemAction;
