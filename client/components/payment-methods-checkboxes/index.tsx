/** @format */
/**
 * External dependencies
 */
import React, { ReactNode } from 'react';

const PaymentMethodsSelector = ( {
	children,
}: {
	children: ReactNode;
} ): React.ReactElement => {
	return <ul>{ children }</ul>;
};

export default PaymentMethodsSelector;
