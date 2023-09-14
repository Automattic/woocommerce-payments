/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { edit } from '@wordpress/icons';
import { createInterpolateElement } from '@wordpress/element';
import { getHistory } from '@woocommerce/navigation';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import wcpayTracks from 'tracks';
import {
	getDisputeFee,
	isAwaitingResponse,
	isInquiry,
} from 'wcpay/disputes/utils';
import { getAdminUrl } from 'wcpay/utils';
import { formatExplicitCurrency } from 'wcpay/utils/currency';
import DisputeNotice from './dispute-notice';
import DisputeSummaryRow from './dispute-summary-row';
import DisputeFooter from './dispute-footer';
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
	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
	const isAccepted = dispute.metadata.__closed_by_merchant === '1';
	const disputeFee = getDisputeFee( dispute );

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
								<DisputeSummaryRow
									dispute={ dispute }
									daysRemaining={ countdownDays }
								/>
							</>
						) }
					</CardBody>
				</Card>
			</div>
		);
	}

	if ( [ 'won', 'lost', 'under_review' ].includes( dispute.status ) ) {
		const closedDateFormatted = dispute.metadata.__dispute_closed_at
			? dateI18n(
					'M j, Y',
					moment
						.unix(
							parseInt( dispute.metadata.__dispute_closed_at, 10 )
						)
						.toISOString()
			  )
			: '-';
		const submissionDateFormatted = dispute.metadata.__evidence_submitted_at
			? dateI18n(
					'M j, Y',
					moment
						.unix(
							parseInt(
								dispute.metadata.__evidence_submitted_at,
								10
							)
						)
						.toISOString()
			  )
			: '-';
		const disputeFeeFormatted = disputeFee
			? formatExplicitCurrency( disputeFee.fee, disputeFee.currency )
			: '-';
		const disputeDocsLinkElement = (
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			<a
				target="_blank"
				rel="noopener noreferrer"
				href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
			/>
		);

		const handleClick = () => {
			wcpayTracks.recordEvent(
				wcpayTracks.events
					.TRANSACTION_DETAILS_DISPUTE_FOOTER_BUTTON_CLICK,
				{
					dispute_status: dispute.status,
					dispute_submitted: isSubmitted,
					dispute_accepted: isAccepted,
				}
			);
			if ( isSubmitted ) {
				const challengeUrl = getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/disputes/challenge',
					id: dispute.id,
				} );
				getHistory().push( challengeUrl );
			}
		};

		if ( dispute.status === 'won' ) {
			return (
				<DisputeFooter
					buttonLabel="View dispute details"
					onButtonClick={ handleClick }
					message={ createInterpolateElement(
						sprintf(
							/* Translators: %s - formatted date, <a> - link to documentation page */
							__(
								'Good news! You won this dispute on %s. The disputed amount and the dispute fee have been credited back to your account. <a>Learn more about preventing disputes</a>.',
								'woocommerce-payments'
							),
							closedDateFormatted
						),
						{ a: disputeDocsLinkElement }
					) }
				/>
			);
		}

		if ( dispute.status === 'under_review' ) {
			return (
				<DisputeFooter
					buttonLabel={ __(
						'View submitted evidence',
						'woocommerce-payments'
					) }
					onButtonClick={ handleClick }
					message={ createInterpolateElement(
						sprintf(
							isInquiry( dispute )
								? /* Translators: %s - formatted date, <a> - link to documentation page */
								  __(
										'You submitted evidence for this inquiry on %s. The cardholder’s bank is reviewing the case, which can take 120 days or more. You will be alerted when they make their final decision. <a>Learn more</a>.',
										'woocommerce-payments'
								  )
								: /* Translators: %s - formatted date, <a> - link to documentation page */
								  __(
										'You submitted evidence for this dispute on %s. The cardholder’s bank is reviewing the case, which can take 60 days or more. You will be alerted when they make their final decision. <a>Learn more about the dispute process</a>.',
										'woocommerce-payments'
								  ),
							submissionDateFormatted
						),
						{ a: disputeDocsLinkElement }
					) }
				/>
			);
		}

		if ( dispute.status === 'lost' ) {
			if ( isAccepted ) {
				return (
					<DisputeFooter
						message={ createInterpolateElement(
							sprintf(
								/* Translators: %1$s - formatted date, %2$s – the formatted dispute fee amount, <a> - link to documentation page */
								__(
									'This dispute was accepted and lost on %1$s. The %2$s fee has been deducted from your account, and the disputed amount returned to the cardholder. <a>Learn more about preventing disputes</a>.',
									'woocommerce-payments'
								),
								closedDateFormatted,
								disputeFeeFormatted
							),
							{ a: disputeDocsLinkElement }
						) }
					/>
				);
			} else if ( isSubmitted ) {
				return (
					<DisputeFooter
						buttonLabel={ __(
							'View dispute details',
							'woocommerce-payments'
						) }
						onButtonClick={ handleClick }
						message={ createInterpolateElement(
							sprintf(
								/* Translators: %1$s - formatted date, %2$s – the formatted dispute fee amount, <a> - link to documentation page */
								__(
									'This dispute was lost on %1$s. The %2$s fee has been deducted from your account, and the disputed amount returned to the cardholder. <a>Learn more about preventing disputes</a>.',
									'woocommerce-payments'
								),
								closedDateFormatted,
								disputeFeeFormatted
							),
							{ a: disputeDocsLinkElement }
						) }
					/>
				);
			}

			// Lost, no challenge submitted
			return (
				<DisputeFooter
					message={ createInterpolateElement(
						sprintf(
							/* Translators: %1$s - formatted date, %2$s – the formatted dispute fee amount, <a> - link to documentation page */
							__(
								'This dispute was lost on %1$s due to non-response. The %2$s fee has been deducted from your account, and the disputed amount returned to the cardholder. <a>Learn more about preventing disputes</a>.',
								'woocommerce-payments'
							),
							closedDateFormatted,
							disputeFeeFormatted
						),
						{ a: disputeDocsLinkElement }
					) }
				/>
			);
		}
	}

	return null;
};

export default DisputeDetails;
