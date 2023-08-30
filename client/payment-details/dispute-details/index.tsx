/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { Card, CardBody } from '@wordpress/components';
import './style.scss';
import DisputeNotice from './dispute-notice';
import { isAwaitingResponse } from 'wcpay/disputes/utils';

interface DisputeDetailsProps {
	dispute: Dispute;
}

const DisputeDetails: React.FC< DisputeDetailsProps > = ( { dispute } ) => {
	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );

	if ( isAwaitingResponse( dispute.status ) ) {
		return (
			<div className="transaction-details-dispute-details-wrapper">
				<Card>
					<CardBody className="transaction-details-dispute-details-body">
						{ countdownDays >= 0 && (
							<DisputeNotice
								dispute={ dispute }
								urgent={ countdownDays <= 2 }
							/>
						) }
						<div></div>
					</CardBody>
				</Card>
			</div>
		);
	}

	return null;
};

export default DisputeDetails;
