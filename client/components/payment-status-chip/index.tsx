/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import displayStatus from './mappings';
import Chip from '../chip';
import { formatStringValue } from 'wcpay/utils';

interface PaymentStatusChipProps {
	status: string;
	className?: string;
}

const PaymentStatusChip = ( { status, className }: PaymentStatusChipProps ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';

	return <Chip className={ className } message={ message } type={ type } />;
};

export default PaymentStatusChip;
