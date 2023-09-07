/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { edit } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import DisputeNotice from './dispute-notice';
import DisputeActions from './dispute-actions';
import { isAwaitingResponse } from 'wcpay/disputes/utils';
import InlineNotice from 'components/inline-notice';
import './style.scss';

interface DisputeDetailsProps {
	dispute: Dispute;
}

const DisputeDetails: React.FC< DisputeDetailsProps > = ( { dispute } ) => {
	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;

  
	if ( isAwaitingResponse( dispute.status ) ) {
		return (
			<div className="transaction-details-dispute-details-wrapper">
				<Card>
					<CardBody className="transaction-details-dispute-details-body">
						{ countdownDays >= 0 && (
              <>
								<DisputeNotice
									dispute={ dispute }
									urgent={ countdownDays <= 2 }
								/>
								{ hasStagedEvidence && (
									<InlineNotice
										icon={ edit }
										isDismissible={ false }
									>
										{ __(
											`You initiated a dispute a challenge to this dispute. Click 'Continue with challenge' to proceed with your drafted response.`,
											'woocommerce-payments'
										) }
									</InlineNotice>
								) }
							</>
						) }
						<DisputeActions dispute={ dispute } />
					</CardBody>
				</Card>
			</div>
		);
	}

	return null;
};

export default DisputeDetails;
