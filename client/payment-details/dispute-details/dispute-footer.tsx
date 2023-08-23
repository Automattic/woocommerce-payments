/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import './style.scss';
import type { Dispute } from 'wcpay/types/disputes';
import {
	Button,
	CardFooter,
	ExternalLink,
	Flex,
	FlexItem,
} from '@wordpress/components';
import { getAdminUrl } from 'wcpay/utils';
import { formatExplicitCurrency } from 'wcpay/utils/currency';

interface DisputeFooterProps {
	dispute: Dispute;
}
const DisputeFooter: React.FC< DisputeFooterProps > = ( { dispute } ) => {
	const isSubmitted = !! dispute.metadata.__evidence_submitted_at;
	const isAccepted = dispute.metadata.__closed_by_merchant === '1';
	const closedDateFormatted =
		dispute.metadata.__dispute_closed_at &&
		dateI18n(
			'M j, Y',
			moment
				.unix( parseInt( dispute.metadata.__dispute_closed_at, 10 ) )
				.toISOString()
		);
	const disputeFee = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute'
	);
	const disputeFeeFormatted =
		disputeFee &&
		formatExplicitCurrency( disputeFee.fee, disputeFee.currency );
	const disputeDocsLinkElement = (
		<ExternalLink href="https://woocommerce.com/document/woopayments/fraud-and-disputes/" />
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
			buttonLabel = __(
				'View submitted evidence',
				'woocommerce-payments'
			);
			message = createInterpolateElement(
				sprintf(
					/* Translators: %s - formatted date, <a> - link to documentation page */
					__(
						'You submitted evidence for this dispute on %s. The cardholder’s bank is reviewing the case, which can take 60 days or more. You will be alerted when they make their final decision. <a>Learn more about the dispute process</a>.',
						'woocommerce-payments'
					),
					closedDateFormatted
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
			}
			if ( isSubmitted ) {
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
			}
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
			break;
	}

	const handleClick = () => {
		// wcpayTracks.recordEvent(
		// 	wcpayTracks.events
		// 		.___
		// )
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
		<CardFooter>
			<Flex justify="space-between">
				<FlexItem>{ message }</FlexItem>
				<FlexItem>
					<Button variant="secondary" onClick={ handleClick }>
						{ buttonLabel }
					</Button>
				</FlexItem>
			</Flex>
		</CardFooter>
	);
};

export default DisputeFooter;
