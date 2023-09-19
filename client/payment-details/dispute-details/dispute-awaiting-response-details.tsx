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
import IssuerEvidenceList from './evidence-list';
import DisputeSummaryRow from './dispute-summary-row';
import InlineNotice from 'components/inline-notice';
import './style.scss';

interface Props {
	dispute: Dispute;
}

const DisputeAwaitingResponseDetails: React.FC< Props > = ( { dispute } ) => {
	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by ?? 0 );
	const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );
	const hasStagedEvidence = dispute.evidence_details?.has_evidence;

	return (
		<div className="transaction-details-dispute-details-wrapper">
			<Card>
				<CardBody className="transaction-details-dispute-details-body">
					<DisputeNotice
						dispute={ dispute }
						urgent={ countdownDays <= 2 }
					/>
					{ hasStagedEvidence && (
						<InlineNotice icon={ edit } isDismissible={ false }>
							{ __(
								`You initiated a challenge to this dispute. Click 'Continue with challenge' to proceed with your draft response.`,
								'woocommerce-payments'
							) }
						</InlineNotice>
					) }
					<DisputeSummaryRow
						dispute={ dispute }
						daysRemaining={ countdownDays }
					/>
					<IssuerEvidenceList
						issuerEvidence={ dispute.issuer_evidence }
					/>
				</CardBody>
			</Card>
		</div>
	);
};

export default DisputeAwaitingResponseDetails;
