/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { createInterpolateElement } from '@wordpress/element';
import { Button, CardFooter, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';
import { getDisputeFeeFormatted } from 'wcpay/disputes/utils';
import './style.scss';

const DisputeUnderReviewFooter: React.FC< {
	dispute: Pick< Dispute, 'id' | 'metadata' | 'status' >;
} > = ( { dispute } ) => {
	const submissionDateFormatted = dispute.metadata.__evidence_submitted_at
		? dateI18n(
				'M j, Y',
				moment
					.unix(
						parseInt( dispute.metadata.__evidence_submitted_at, 10 )
					)
					.toISOString()
		  )
		: '-';

	return (
		<CardFooter className="transaction-details-dispute-footer transaction-details-dispute-footer--primary">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						sprintf(
							/* Translators: %s - formatted date, <a> - link to documentation page */
							__(
								'You submitted evidence for this dispute on %s. The cardholder’s bank is reviewing the case, which can take 60 days or more. You will be alerted when they make their final decision. <a>Learn more about the dispute process</a>.',
								'woocommerce-payments'
							),
							submissionDateFormatted
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- Link content is provided by createInterpolateElement
								<a
									target="_blank"
									rel="noopener noreferrer"
									href="https://woo.com/document/woopayments/fraud-and-disputes/"
								/>
							),
						}
					) }
				</FlexItem>
				<FlexItem className="transaction-details-dispute-footer__actions">
					<Link
						href={ getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/disputes/challenge',
							id: dispute.id,
						} ) }
					>
						<Button
							variant="secondary"
							onClick={ () => {
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED,
									{
										dispute_status: dispute.status,
										on_page: 'transaction_details',
									}
								);
							} }
						>
							{ __(
								'View submitted evidence',
								'woocommerce-payments'
							) }
						</Button>
					</Link>
				</FlexItem>
			</Flex>
		</CardFooter>
	);
};

const DisputeWonFooter: React.FC< {
	dispute: Pick< Dispute, 'id' | 'metadata' | 'status' >;
} > = ( { dispute } ) => {
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

	return (
		<CardFooter className="transaction-details-dispute-footer">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						sprintf(
							/* Translators: %s - formatted date, <a> - link to documentation page */
							__(
								'Good news! You won this dispute on %s. The disputed amount and the dispute fee have been credited back to your account. <a>Learn more about preventing disputes</a>.',
								'woocommerce-payments'
							),
							closedDateFormatted
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- Link content is provided by createInterpolateElement
								<a
									target="_blank"
									rel="noopener noreferrer"
									href="https://woo.com/document/woopayments/fraud-and-disputes/"
								/>
							),
						}
					) }
				</FlexItem>
				<FlexItem className="transaction-details-dispute-footer__actions">
					<Link
						href={ getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/disputes/challenge',
							id: dispute.id,
						} ) }
					>
						<Button
							variant="secondary"
							onClick={ () => {
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED,
									{
										dispute_status: dispute.status,
										on_page: 'transaction_details',
									}
								);
							} }
						>
							{ __(
								'View dispute details',
								'woocommerce-payments'
							) }
						</Button>
					</Link>
				</FlexItem>
			</Flex>
		</CardFooter>
	);
};

const DisputeLostFooter: React.FC< {
	dispute: Pick<
		Dispute,
		'id' | 'metadata' | 'status' | 'balance_transactions'
	>;
} > = ( { dispute } ) => {
	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
	const isAccepted = dispute.metadata.__closed_by_merchant === '1';
	const disputeFeeFormatted = getDisputeFeeFormatted( dispute, true ) ?? '-';

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

	let messagePrefix = sprintf(
		/* Translators: %1$s - formatted date */
		__(
			'This dispute was lost on %1$s due to non-response.',
			'woocommerce-payments'
		),
		closedDateFormatted
	);

	if ( isAccepted ) {
		messagePrefix = sprintf(
			/* Translators: %1$s - formatted date */
			__(
				'This dispute was accepted and lost on %1$s.',
				'woocommerce-payments'
			),
			closedDateFormatted
		);
	}

	if ( isSubmitted ) {
		messagePrefix = sprintf(
			/* Translators: %1$s - formatted date */
			__( 'This dispute was lost on %1$s.', 'woocommerce-payments' ),
			closedDateFormatted
		);
	}

	return (
		<CardFooter className="transaction-details-dispute-footer">
			<Flex justify="space-between">
				<FlexItem>
					{ messagePrefix }{ ' ' }
					{ createInterpolateElement(
						sprintf(
							/* Translators: %1$s – the formatted dispute fee amount, <a> - link to documentation page */
							__(
								'The %1$s fee has been deducted from your account, and the disputed amount returned to the cardholder. <a>Learn more about preventing disputes</a>.',
								'woocommerce-payments'
							),
							disputeFeeFormatted
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- Link content is provided by createInterpolateElement
								<a
									target="_blank"
									rel="noopener noreferrer"
									href="https://woo.com/document/woopayments/fraud-and-disputes/"
								/>
							),
						}
					) }
				</FlexItem>

				{ isSubmitted && (
					<FlexItem className="transaction-details-dispute-footer__actions">
						<Link
							href={ getAdminUrl( {
								page: 'wc-admin',
								path: '/payments/disputes/challenge',
								id: dispute.id,
							} ) }
						>
							<Button
								variant="secondary"
								onClick={ () => {
									wcpayTracks.recordEvent(
										wcpayTracks.events
											.PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED,
										{
											dispute_status: dispute.status,
											on_page: 'transaction_details',
										}
									);
								} }
							>
								{ __(
									'View dispute details',
									'woocommerce-payments'
								) }
							</Button>
						</Link>
					</FlexItem>
				) }
			</Flex>
		</CardFooter>
	);
};

const InquiryUnderReviewFooter: React.FC< {
	dispute: Pick< Dispute, 'id' | 'metadata' | 'status' >;
} > = ( { dispute } ) => {
	const submissionDateFormatted = dispute.metadata.__evidence_submitted_at
		? dateI18n(
				'M j, Y',
				moment
					.unix(
						parseInt( dispute.metadata.__evidence_submitted_at, 10 )
					)
					.toISOString()
		  )
		: '-';

	return (
		<CardFooter className="transaction-details-dispute-footer transaction-details-dispute-footer--primary">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						sprintf(
							/* Translators: %s - formatted date, <a> - link to documentation page */
							__(
								'You submitted evidence for this inquiry on %s. The cardholder’s bank is reviewing the case, which can take 120 days or more. You will be alerted when they make their final decision. <a>Learn more</a>.',
								'woocommerce-payments'
							),
							submissionDateFormatted
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- Link content is provided by createInterpolateElement
								<a
									target="_blank"
									rel="noopener noreferrer"
									href="https://woo.com/document/woopayments/fraud-and-disputes/managing-disputes/#inquiries"
								/>
							),
						}
					) }
				</FlexItem>
				<FlexItem className="transaction-details-dispute-footer__actions">
					<Link
						href={ getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/disputes/challenge',
							id: dispute.id,
						} ) }
					>
						<Button
							variant="secondary"
							onClick={ () => {
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED,
									{
										dispute_status: dispute.status,
										on_page: 'transaction_details',
									}
								);
							} }
						>
							{ __(
								'View submitted evidence',
								'woocommerce-payments'
							) }
						</Button>
					</Link>
				</FlexItem>
			</Flex>
		</CardFooter>
	);
};

const InquiryClosedFooter: React.FC< {
	dispute: Pick< Dispute, 'id' | 'metadata' | 'status' >;
} > = ( { dispute } ) => {
	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
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

	return (
		<CardFooter className="transaction-details-dispute-footer">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						sprintf(
							/* Translators: %s - formatted date, <a> - link to documentation page */
							__(
								'This inquiry was closed on %s. <a>Learn more about preventing disputes</a>.',
								'woocommerce-payments'
							),
							closedDateFormatted
						),
						{
							a: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content -- Link content is provided by createInterpolateElement
								<a
									target="_blank"
									rel="noopener noreferrer"
									href="https://woo.com/document/woopayments/fraud-and-disputes/"
								/>
							),
						}
					) }
				</FlexItem>

				{ isSubmitted && (
					<FlexItem className="transaction-details-dispute-footer__actions">
						<Link
							href={ getAdminUrl( {
								page: 'wc-admin',
								path: '/payments/disputes/challenge',
								id: dispute.id,
							} ) }
						>
							<Button
								variant="secondary"
								onClick={ () => {
									wcpayTracks.recordEvent(
										wcpayTracks.events
											.PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED,
										{
											dispute_status: dispute.status,
											on_page: 'transaction_details',
										}
									);
								} }
							>
								{ __(
									'View submitted evidence',
									'woocommerce-payments'
								) }
							</Button>
						</Link>
					</FlexItem>
				) }
			</Flex>
		</CardFooter>
	);
};

const DisputeResolutionFooter: React.FC< {
	dispute: Pick<
		Dispute,
		'id' | 'metadata' | 'status' | 'balance_transactions'
	>;
} > = ( { dispute } ) => {
	if ( dispute.status === 'under_review' ) {
		return <DisputeUnderReviewFooter dispute={ dispute } />;
	}
	if ( dispute.status === 'won' ) {
		return <DisputeWonFooter dispute={ dispute } />;
	}
	if ( dispute.status === 'lost' ) {
		return <DisputeLostFooter dispute={ dispute } />;
	}
	if ( dispute.status === 'warning_under_review' ) {
		return <InquiryUnderReviewFooter dispute={ dispute } />;
	}
	if ( dispute.status === 'warning_closed' ) {
		return <InquiryClosedFooter dispute={ dispute } />;
	}

	return null;
};

export default DisputeResolutionFooter;
