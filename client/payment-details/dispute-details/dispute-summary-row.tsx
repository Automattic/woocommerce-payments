/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { HorizontalList } from 'wcpay/components/horizontal-list';
import { formatExplicitCurrency } from 'wcpay/utils/currency';
import { reasons } from 'wcpay/disputes/strings';
import { formatStringValue } from 'wcpay/utils';
import { ClickTooltip } from 'wcpay/components/tooltip';
import Paragraphs from 'wcpay/components/paragraphs';
import DisputeDueByDate from './dispute-due-by-date';

interface Props {
	dispute: Dispute;
}

const DisputeSummaryRow: React.FC< Props > = ( { dispute } ) => {
	const disputeReason = formatStringValue(
		reasons[ dispute.reason ]?.display || dispute.reason
	);
	const disputeReasonSummary = reasons[ dispute.reason ]?.summary || [];

	const columns = [
		{
			title: __( 'Dispute Amount', 'woocommerce-payments' ),
			content: formatExplicitCurrency( dispute.amount, dispute.currency ),
		},
		{
			title: __( 'Disputed On', 'woocommerce-payments' ),
			content: dispute.created
				? dateI18n(
						'M j, Y, g:ia',
						moment( dispute.created * 1000 ).toISOString()
				  )
				: '–',
		},
		{
			title: __( 'Reason', 'woocommerce-payments' ),
			content: (
				<>
					{ disputeReason }
					{ disputeReasonSummary.length > 0 && (
						<ClickTooltip
							buttonIcon={ <HelpOutlineIcon /> }
							buttonLabel={ __(
								'Learn more',
								'woocommerce-payments'
							) }
							content={
								<div className="dispute-reason-tooltip">
									<p>{ disputeReason }</p>
									<Paragraphs>
										{ disputeReasonSummary }
									</Paragraphs>
									<p>
										<a
											href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</a>
									</p>
								</div>
							}
						/>
					) }
				</>
			),
		},
		{
			title: __( 'Respond By', 'woocommerce-payments' ),
			content: (
				<DisputeDueByDate dueBy={ dispute.evidence_details.due_by } />
			),
		},
	];

	return (
		<div className="dispute-summary-row">
			<HorizontalList items={ columns } />
		</div>
	);
};

export default DisputeSummaryRow;
