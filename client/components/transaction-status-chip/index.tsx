/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import transactionStatusMapping, { TransactionStatus } from './mappings';
import { formatStringValue } from 'utils';

interface TransactionStatusChipProps {
	status: TransactionStatus;
}

const TransactionStatusChip = ( {
	status,
}: TransactionStatusChipProps ): JSX.Element => {
	const mapping = transactionStatusMapping[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';

	return <Chip message={ message } type={ type } isCompact />;
};

export default TransactionStatusChip;
