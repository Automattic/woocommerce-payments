/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import { createInterpolateElement } from '@wordpress/element';
import { Button, CardFooter, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { recordEvent } from 'tracks';
import { getAdminUrl } from 'wcpay/utils';
import { getDisputeFeeFormatted } from 'wcpay/disputes/utils';
import './style.scss';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';

const DisputeUnderReviewFooter: React.FC< {
	dispute: Pick< Dispute, 'id' | 'metadata' | 'status' >;
	bankName: string | null;
} > = ( { dispute, bankName } ) => {
	const submissionDateFormatted = dispute.metadata.__evidence_submitted_at
		? formatDateTimeFromTimestamp(
				parseInt( dispute.metadata.__evidence_submitted_at, 10 )
		  )
		: '-';

	return (
		<CardFooter className="transaction-details-dispute-footer transaction-details-dispute-footer--primary">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						bankName
							? sprintf(
									/* Translators: %1$s - bank name, %2$s - formatted date, <a> - link to documentation page */
									__(
										"<strong>The customer's bank, %1$s, is currently reviewing the evidence you submitted on %2$s.</strong> This process can sometimes take more than 60 days — we'll let you know once a decision has been made. <a>Learn more about the dispute process.</a>",
										'woocommerce-payments'
									),
									bankName,
									submissionDateFormatted
							  )
							: sprintf(
									/* Translators: %s - formatted date, <a> - link to documentation page */
									__(
										"<strong>The customer's bank is currently reviewing the evidence you submitted on %1$s.</strong> This process can sometimes take more than 60 days — we'll let you know once a decision has been made. <a>Learn more about the dispute process.</a>",
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
									href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
								/>
							),
							strong: <strong />,
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
								recordEvent(
									'wcpay_view_submitted_evidence_clicked',
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
	bankName: string | null;
} > = ( { dispute, bankName } ) => {
	const closedDateFormatted = dispute.metadata.__dispute_closed_at
		? formatDateTimeFromTimestamp(
				parseInt( dispute.metadata.__dispute_closed_at, 10 )
		  )
		: '-';

	return (
		<CardFooter className="transaction-details-dispute-footer">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						bankName
							? sprintf(
									/* Translators: %1$s - bank name, %2$s - formatted date, <a> - link to documentation page */
									__(
										"<strong>Good news — you've won this dispute! The customer's bank, %1$s, reached this decision on %2$s.</strong> Your account has been credited with the disputed amount and fee. <a>Learn more about preventing disputes.</a>",
										'woocommerce-payments'
									),
									bankName,
									closedDateFormatted
							  )
							: sprintf(
									/* Translators: %s - formatted date, <a> - link to documentation page */
									__(
										"<strong>Good news — you've won this dispute! The customer's bank reached this decision on %1$s.</strong> Your account has been credited with the disputed amount and fee. <a>Learn more about preventing disputes.</a>",
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
									href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
								/>
							),
							strong: <strong />,
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
								recordEvent(
									'wcpay_view_submitted_evidence_clicked',
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
	bankName: string | null;
} > = ( { dispute, bankName } ) => {
	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
	const isAccepted = dispute.metadata.__closed_by_merchant === '1';
	const disputeFeeFormatted = getDisputeFeeFormatted( dispute, true ) ?? '-';

	const closedDateFormatted = dispute.metadata.__dispute_closed_at
		? formatDateTimeFromTimestamp(
				parseInt( dispute.metadata.__dispute_closed_at, 10 )
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
				'<strong>You accepted this dispute on %1$s.</strong>',
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

	if ( isSubmitted ) {
		if ( bankName ) {
			messagePrefix = sprintf(
				/* Translators: %1$s - bank name, %2$s - formatted date */
				__(
					"<strong>Unfortunately, you've lost this dispute. The customer's bank, %1$s, reached this decision on %2$s.</strong>",
					'woocommerce-payments'
				),
				bankName,
				closedDateFormatted
			);
		} else {
			messagePrefix = sprintf(
				/* Translators: %s - formatted date */
				__(
					"<strong>Unfortunately, you've lost this dispute. The customer's bank reached this decision on %1$s.</strong>",
					'woocommerce-payments'
				),
				closedDateFormatted
			);
		}
	}

	return (
		<CardFooter className="transaction-details-dispute-footer">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement( messagePrefix, {
						strong: <strong />,
					} ) }{ ' ' }
					{ createInterpolateElement(
						sprintf(
							/* Translators: %1$s – the formatted dispute fee amount, <a> - link to documentation page */
							__(
								'The %1$s fee has been deducted from your account, and the disputed amount has been returned to your customer. <a>Learn more about preventing disputes</a>.',
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
									href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
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
									recordEvent(
										'wcpay_view_submitted_evidence_clicked',
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
	bankName: string | null;
} > = ( { dispute, bankName } ) => {
	const submissionDateFormatted = dispute.metadata.__evidence_submitted_at
		? formatDateTimeFromTimestamp(
				parseInt( dispute.metadata.__evidence_submitted_at, 10 )
		  )
		: '-';

	return (
		<CardFooter className="transaction-details-dispute-footer transaction-details-dispute-footer--primary">
			<Flex justify="space-between">
				<FlexItem>
					{ createInterpolateElement(
						bankName
							? sprintf(
									/* Translators: %1$s - bank name, %2$s - formatted date, <a> - link to documentation page */
									__(
										'You submitted evidence for this inquiry on %1$s. <strong>%2$s</strong> is reviewing the case, which can take 120 days or more. You will be alerted when they make their final decision. <a>Learn more</a>.',
										'woocommerce-payments'
									),
									submissionDateFormatted,
									bankName
							  )
							: sprintf(
									/* Translators: %s - formatted date, <a> - link to documentation page */
									__(
										'You submitted evidence for this inquiry on %s. The <strong>cardholder’s bank</strong> is reviewing the case, which can take 120 days or more. You will be alerted when they make their final decision. <a>Learn more</a>.',
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
									href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#inquiries"
								/>
							),
							strong: <strong />,
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
								recordEvent(
									'wcpay_view_submitted_evidence_clicked',
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
		? formatDateTimeFromTimestamp(
				parseInt( dispute.metadata.__dispute_closed_at, 10 )
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
									href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
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
									recordEvent(
										'wcpay_view_submitted_evidence_clicked',
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
	bankName: string | null;
} > = ( { dispute, bankName } ) => {
	if ( dispute.status === 'under_review' ) {
		return (
			<DisputeUnderReviewFooter
				dispute={ dispute }
				bankName={ bankName }
			/>
		);
	}
	if ( dispute.status === 'won' ) {
		return <DisputeWonFooter dispute={ dispute } bankName={ bankName } />;
	}
	if ( dispute.status === 'lost' ) {
		return <DisputeLostFooter dispute={ dispute } bankName={ bankName } />;
	}
	if ( dispute.status === 'warning_under_review' ) {
		return (
			<InquiryUnderReviewFooter
				dispute={ dispute }
				bankName={ bankName }
			/>
		);
	}
	if ( dispute.status === 'warning_closed' ) {
		return <InquiryClosedFooter dispute={ dispute } />;
	}

	return null;
};

export default DisputeResolutionFooter;
