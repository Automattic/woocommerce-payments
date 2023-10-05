/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from 'utils';
import { isAwaitingResponse, isDueWithin } from 'wcpay/disputes/utils';
import type {
	CachedDispute,
	DisputeStatus,
	EvidenceDetails,
} from 'wcpay/types/disputes';

interface Props {
	status: DisputeStatus | string;
	dueBy?: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ];
	includeTransactionType?: boolean;
}
const DisputeStatusChip: React.FC< Props > = ( {
	status,
	dueBy,
	includeTransactionType,
} ) => {
	const mapping = displayStatus[ status ] || {};
	let message = mapping.message || formatStringValue( status );

	// Statuses starting with warning_ are Inquiries and these are already prefaced with "Inquiry: "
	if ( includeTransactionType && ! status.startsWith( 'warning' ) ) {
		message = sprintf(
			/** translators: %s is the status of the Dispute. */
			__( 'Disputed: %s', 'woocommerce-payments' ),
			message
		);
	}

	const needsResponse = isAwaitingResponse( status );
	const isUrgent =
		needsResponse && dueBy && isDueWithin( { dueBy, days: 3 } );

	let type = mapping.type || 'light';
	if ( isUrgent ) {
		type = 'alert';
	}

	return <Chip message={ message } type={ type } />;
};

export default DisputeStatusChip;
