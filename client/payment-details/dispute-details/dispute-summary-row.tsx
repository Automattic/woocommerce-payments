/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import classNames from 'classnames';

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
import { getDisputeDeductedBalanceTransaction } from 'wcpay/disputes/utils';

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
	const disputeBalanceTransaction = getDisputeDeductedBalanceTransaction(
		dispute
	);
	// If there is a dispute deduction balance transaction, show the dispute amount in the store's currency.
	// Otherwise (if the dispute is an inquiry) use the dispute/charge amount and currency.
	const disputeAmountFormatted = disputeBalanceTransaction
		? formatExplicitCurrency(
				Math.abs( disputeBalanceTransaction.amount ),
				disputeBalanceTransaction.currency
		  )
		: formatExplicitCurrency( dispute.amount, dispute.currency );

	const columns = [
		{
			title: __( 'Dispute Amount', 'woocommerce-payments' ),
			content: disputeAmountFormatted,
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
				<span className="dispute-summary-row__response-date">
					{ respondByDate }
					<span
						className={ classNames( {
							'dispute-summary-row__response-date--urgent':
								daysRemaining < 3,
							'dispute-summary-row__response-date--warning':
								daysRemaining < 7 && daysRemaining > 2,
						} ) }
					>
						{ daysRemaining > 0 &&
							sprintf(
								// Translators: %s is the number of days left to respond to the dispute.
								_n(
									'(%s day left to respond)',
									'(%s days left to respond)',
									daysRemaining,
									'woocommerce-payments'
								),
								daysRemaining
							) }

						{ daysRemaining === 0 &&
							__( '(Last day today)', 'woocommerce-payments' ) }
						{ daysRemaining < 0 &&
							__( '(Past due)', 'woocommerce-payments' ) }
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
