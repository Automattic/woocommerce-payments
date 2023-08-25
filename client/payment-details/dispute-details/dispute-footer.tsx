/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import classNames from 'classnames';
import { dateI18n } from '@wordpress/date';
import { getHistory } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { Button, CardFooter, Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import type { Dispute } from 'wcpay/types/disputes';
import { getAdminUrl } from 'wcpay/utils';
import { formatExplicitCurrency } from 'wcpay/utils/currency';
import { isInquiry } from 'wcpay/disputes/utils';
import './style.scss';

const formatUnixTimestamp = ( date: string | undefined, format: string ) =>
	date &&
	dateI18n( format, moment.unix( parseInt( date, 10 ) ).toISOString() );

interface DisputeFooterProps {
	dispute: Dispute;
}

const DisputeFooter: React.FC< DisputeFooterProps > = ( { dispute } ) => {
	const isValidStatus = [
		'won',
		'lost',
		'under_review',
		'warning_under_review',
		// TODO: confirm if the following should render:
		// 'charge_refunded', 'warning_closed'
	].includes( dispute.status );

	if ( ! isValidStatus ) {
		return null;
	}

	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
	const isAccepted = dispute.metadata.__closed_by_merchant === '1';
	const closedDateFormatted = formatUnixTimestamp(
		dispute.metadata.__dispute_closed_at,
		'M j, Y'
	);

	const disputeFee = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute'
	);
	const disputeFeeFormatted =
		disputeFee &&
		formatExplicitCurrency( disputeFee.fee, disputeFee.currency );
	const disputeDocsLinkElement = (
		// eslint-disable-next-line jsx-a11y/anchor-has-content
		<a
			target="_blank"
			rel="noopener noreferrer"
			href="https://woocommerce.com/document/woopayments/fraud-and-disputes/"
		/>
	);

	let message = null;
	let buttonLabel = __( 'View dispute details', 'woocommerce-payments' );

	switch ( dispute.status ) {
		case 'won':
			message = createInterpolateElement(
				sprintf(
					/* Translators: %s - formatted date, <a> - link to documentation page */
					__(
						'Good news! You won this dispute on %s. The disputed amount and the dispute fee have been credited back to your account. <a>Learn more about preventing disputes</a>.',
						'woocommerce-payments'
					),
					closedDateFormatted
				),
				{ a: disputeDocsLinkElement }
			);
			break;
		case 'under_review':
		case 'warning_under_review':
			const submissionDateFormatted = formatUnixTimestamp(
				dispute.metadata.__evidence_submitted_at,
				'M j, Y'
			);

			buttonLabel = __(
				'View submitted evidence',
				'woocommerce-payments'
			);
			message = createInterpolateElement(
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
			);
			break;
		case 'lost':
			if ( isAccepted ) {
				// Lost accepted
				message = createInterpolateElement(
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
				);
			} else if ( isSubmitted ) {
				// Lost
				message = createInterpolateElement(
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
				);
			} else {
				// Lost no response
				message = createInterpolateElement(
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
				);
			}
			break;
	}

	const handleClick = () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.TRANSACTION_DETAILS_DISPUTE_FOOTER_BUTTON_CLICK,
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
			const history = getHistory();
			history.push( challengeUrl );
		} else {
			// TODO: Open issuer_evidence PDF in a new window
		}
	};

	return (
		<CardFooter
			className={ classNames( 'transaction-details-dispute-footer', {
				'transaction-details-dispute-footer--primary':
					dispute.status === 'under_review',
			} ) }
		>
			<Flex justify="space-between">
				<FlexItem>{ message }</FlexItem>
				<FlexItem className="transaction-details-dispute-footer__actions">
					<Button variant="secondary" onClick={ handleClick }>
						{ buttonLabel }
					</Button>
				</FlexItem>
			</Flex>
		</CardFooter>
	);
};

export default DisputeFooter;
