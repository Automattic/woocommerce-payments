/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodItemBodyProps } from './types';

const PaymentMethodItemBody: React.FC< PaymentMethodItemBodyProps > = ( {
	children,
} ) => <div className="payment-method-item__text-container">{ children }</div>;

PaymentMethodItemBody.displayName = 'PaymentMethodItemBody';

export default PaymentMethodItemBody;
