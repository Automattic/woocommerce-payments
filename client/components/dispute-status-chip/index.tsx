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
import { isAwaitingKlarna, isAwaitingResponse } from 'wcpay/disputes/utils';
import type { DisputeStatus } from 'wcpay/types/disputes';

interface Props {
	status: DisputeStatus | string;
	prefixDisputeType?: boolean;
	className?: string;
	/** The payment method type, eg `klarna`. */
	paymentMethod?: string | null;
	/** Whether the dispute's response deadline has passed. */
	isPastDue?: boolean;
}
const DisputeStatusChip: React.FC< Props > = ( {
	status,
	prefixDisputeType,
	className,
	paymentMethod,
	isPastDue = false,
} ) => {
	const mapping = displayStatus[ status ] || {};
	let message = mapping.message || formatStringValue( status );

	const awaitingKlarna = isAwaitingKlarna( status, paymentMethod );
	if ( awaitingKlarna ) {
		message = __( 'Inquiry: Awaiting Klarna', 'woocommerce-payments' );
	}

	// Statuses starting with warning_ are Inquiries and these are already prefaced with "Inquiry: "
	if ( prefixDisputeType && ! status.startsWith( 'warning' ) ) {
		message = sprintf(
			/** translators: %s is the status of the Dispute. */
			__( 'Disputed: %s', 'woocommerce-payments' ),
			message
		);
	}

	// All the "Needs response" states make red, regardless of the time left
	const isUrgent = isAwaitingResponse( status );

	let type = mapping.type || 'light';
	if ( awaitingKlarna && isPastDue ) {
		// Past its window, a Klarna inquiry only waits on Klarna's decision.
		type = 'primary';
	} else if ( isUrgent ) {
		type = 'alert';
	}

	return <Chip className={ className } message={ message } type={ type } />;
};

export default DisputeStatusChip;
