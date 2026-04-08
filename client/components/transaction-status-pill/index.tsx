/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import Pill from '../pill';

/**
 * Internal dependencies
 */
import transactionStatusMapping, { TransactionStatus } from './mappings';
import { formatStringValue } from 'utils';

interface TransactionStatusPillProps {
	status: TransactionStatus;
}

const TransactionStatusPill: React.FC< TransactionStatusPillProps > = ( {
	status,
} ) => {
	const mapping = transactionStatusMapping[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type = mapping.type || 'light';

	return <Pill type={ type }>{ message }</Pill>;
};

export default TransactionStatusPill;
