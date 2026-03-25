/**
 * External dependencies
 */
import clsx from 'clsx';
import React from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemSubgroupProps } from './types';

const PaymentMethodItemSubgroup: React.FC< PaymentMethodItemSubgroupProps > = ( {
	Icon,
	label,
	children,
	className,
} ) => (
	<div className={ clsx( 'payment-method-item__subgroup', className ) }>
		{ Icon && (
			<div className="payment-method-item__icon">
				<Icon />
			</div>
		) }
		<div className="payment-method-item__label payment-method-item__label--mobile">
			{ label }
		</div>
		<div className="payment-method-item__label-container">
			<div className="payment-method-item__label payment-method-item__label--desktop">
				{ label }
			</div>
			{ children && (
				<div className="payment-method-item__description">
					{ children }
				</div>
			) }
		</div>
	</div>
);

PaymentMethodItemSubgroup.displayName = 'PaymentMethodItemSubgroup';

export default PaymentMethodItemSubgroup;
