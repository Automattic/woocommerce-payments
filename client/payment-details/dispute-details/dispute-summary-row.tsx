/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { HorizontalList } from 'wcpay/components/horizontal-list';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { reasons } from 'wcpay/disputes/strings';
import { formatStringValue } from 'wcpay/utils';
import { ClickTooltip } from 'wcpay/components/tooltip';
import DisputeDueByDate from './dispute-due-by-date';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import { ExternalLink } from '@wordpress/components';
import { isAwaitingKlarna, isKlarnaInquiryPastDue } from 'wcpay/disputes/utils';

interface Props {
	dispute: Pick<
		Dispute,
		| 'amount'
		| 'created'
		| 'currency'
		| 'evidence_details'
		| 'reason'
		| 'status'
	>;
	paymentMethod?: string | null;
}

const getDueByColumn = (
	dispute: Props[ 'dispute' ],
	paymentMethod?: string | null
) => {
	const dueBy = dispute.evidence_details.due_by;

	if ( isKlarnaInquiryPastDue( dispute.status, paymentMethod, dueBy ) ) {
		return {
			title: __( 'Resolution window closed', 'woocommerce-payments' ),
			content: (
				<span className="dispute-steps__steps__response-date">
					{ formatDateTimeFromTimestamp( dueBy, {
						customFormat: 'F j, Y g:i A',
					} ) }
					<span className="dispute-steps__steps__response-date--pending">
						{ __(
							" (Pending Klarna's decision)",
							'woocommerce-payments'
						) }
					</span>
				</span>
			),
		};
	}

	if ( isAwaitingKlarna( dispute.status, paymentMethod ) ) {
		return {
			title: __( 'Resolve By', 'woocommerce-payments' ),
			content: <DisputeDueByDate dueBy={ dueBy } isResolutionWindow />,
		};
	}

	return {
		title: __( 'Respond By', 'woocommerce-payments' ),
		content: <DisputeDueByDate dueBy={ dueBy } />,
	};
};

const DisputeSummaryRow: React.FC< Props > = ( { dispute, paymentMethod } ) => {
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
				? formatDateTimeFromTimestamp( dispute.created, {
						separator: ', ',
						includeTime: false,
					} )
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
									<p>
										{ disputeReasonSummary }{ ' ' }
										<ExternalLink href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/">
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</ExternalLink>
									</p>
								</div>
							}
						/>
					) }
				</>
			),
		},
		getDueByColumn( dispute, paymentMethod ),
	];

	return (
		<div className="dispute-summary-row">
			<HorizontalList items={ columns } />
		</div>
	);
};

export default DisputeSummaryRow;
