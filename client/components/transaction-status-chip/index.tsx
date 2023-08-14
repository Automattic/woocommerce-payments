/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import Chip from '../chip';

/**
 * Internal dependencies
 */
import transactionStatusMapping, { TransactionStatus } from './mappings';
import { formatStringValue } from 'utils';

interface TransactionStatusChipProps {
	status: TransactionStatus;
}

const TransactionStatusChip: React.FC< TransactionStatusChipProps > = ( {
	status,
	...props
} ) => {
	const mapping = transactionStatusMapping[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';

	return <Chip message={ message } type={ type } { ...props } />;
};

export default TransactionStatusChip;
