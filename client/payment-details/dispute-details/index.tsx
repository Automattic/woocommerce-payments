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
import { isAwaitingResponse } from 'wcpay/disputes/utils';
import DisputeNotice from './dispute-notice';
import DisputeSummaryRow from './dispute-summary-row';
import DisputeSteps from './dispute-steps';
import InlineNotice from 'components/inline-notice';
import './style.scss';
import { ChargeBillingDetails } from 'wcpay/types/charges';

interface DisputeDetailsProps {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
}

const DisputeDetails: React.FC< DisputeDetailsProps > = ( {
	dispute,
	customer,
	chargeCreated,
} ) => {
	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;

	return (
		<div className="transaction-details-dispute-details-wrapper">
			<Card>
				<CardBody className="transaction-details-dispute-details-body">
					{ isAwaitingResponse( dispute.status ) &&
						countdownDays >= 0 && (
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
								<DisputeSummaryRow
									dispute={ dispute }
									daysRemaining={ countdownDays }
								/>
								<DisputeSteps
									dispute={ dispute }
									customer={ customer }
									chargeCreated={ chargeCreated }
									daysRemaining={ countdownDays }
								/>
							</>
						) }
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeDetails;
