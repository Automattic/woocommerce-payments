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
import './style.scss';

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

	return (
		<Chip
			message={ message }
			type={ type }
			isCompact
			{ ...props }
			className="chip--transaction"
		/>
	);
};

export default TransactionStatusChip;
