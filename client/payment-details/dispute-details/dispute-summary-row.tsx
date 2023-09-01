/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { HorizontalList } from 'wcpay/components/horizontal-list';
import { formatCurrency } from 'wcpay/utils/currency';
import { reasons } from 'wcpay/disputes/strings';
import { formatStringValue } from 'wcpay/utils';
import { ClickTooltip } from 'wcpay/components/tooltip';
import Paragraphs from 'wcpay/components/paragraphs';

interface Props {
	dispute: Dispute;
	daysRemaining: number;
}

const DisputeSummaryRow: React.FC< Props > = ( { dispute, daysRemaining } ) => {
	const respondByDate = dispute.evidence_details?.due_by
		? dateI18n(
				'M j, Y, g:ia',
				moment( dispute.evidence_details?.due_by * 1000 ).toISOString()
		  )
		: '–';

	const disputeReason = formatStringValue(
		reasons[ dispute.reason ]?.display || dispute.reason
	);
	const disputeReasonSummary = reasons[ dispute.reason ]?.summary || [];

	const columns = [
		{
			title: __( 'Dispute Amount', 'woocommerce-payments' ),
			content: formatCurrency( dispute.amount, dispute.currency ),
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
						></ClickTooltip>
					) }
				</>
			),
		},
		{
			title: __( 'Respond By', 'woocommerce-payments' ),
			content: (
				<span className="response-date">
					{ respondByDate }
					<span
						className={
							( daysRemaining < 3 ? 'dispute-urgent ' : '' ) +
							( daysRemaining < 7 && daysRemaining > 2
								? 'dispute-warning '
								: '' )
						}
					>
						{ daysRemaining === 0
							? __( '(Last day today)', 'woocommerce-payments' )
							: sprintf(
									// Translators: %s is the number of days left to respond to the dispute.
									_n(
										'(%s day left to respond)',
										'(%s days left to respond)',
										daysRemaining,
										'woocommerce-payments'
									),
									daysRemaining
							  ) }
					</span>
				</span>
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
