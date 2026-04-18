/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import InlineNotice from 'components/inline-notice';
import { reasons } from 'wcpay/disputes/strings';
import { Dispute } from 'wcpay/types/disputes';
import { isInquiry } from 'wcpay/disputes/utils';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';

interface DisputeNoticeProps {
	dispute: Dispute;
	isUrgent: boolean;
	paymentMethod: string | null;
	bankName: string | null;
}

const DisputeNotice: React.FC< DisputeNoticeProps > = ( {
	dispute,
	isUrgent,
	paymentMethod,
	bankName,
} ) => {
	const shopperDisputeReason =
		reasons[ dispute.reason ]?.claim ??
		__(
			'The cardholder claims this is an unauthorized charge.',
			'woocommerce-payments'
		);

	// Format the deadline date
	const dueByDate = formatDateTimeFromTimestamp(
		dispute.evidence_details?.due_by ?? 0,
		{
			customFormat: 'g:i A \\o\\n F j, Y',
		}
	);

	// Determine the appropriate notice text based on dispute type and reason
	let noticeText = '';
	const noticeElements: Record< string, JSX.Element > = {
		strong: <strong />,
	};

	// Handle Klarna inquiries specifically — per-reason copy
	if ( paymentMethod === 'klarna' && isInquiry( dispute.status ) ) {
		if ( dispute.reason === 'credit_not_processed' ) {
			/* translators: %s is the deadline date, eg "11:59 PM on Aug 5, 2026". */
			noticeText = sprintf(
				__(
					"<strong>The customer has filed an inquiry through Klarna, reporting a return.</strong> This is a standard part of Klarna's returns process. Once you receive the item, issue the refund as usual. If it remains unresolved by %s, the inquiry may escalate to a dispute, which you can challenge with evidence. <link>Learn more about Klarna inquiries and disputes</link>",
					'woocommerce-payments'
				),
				dueByDate
			);
			noticeElements.link = (
				// @ts-expect-error: children is provided when interpolating the component
				<ExternalLink href="https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#klarna-inquiries-returns" />
			);
		} else {
			const klarnaReasonClauses: Record< string, string > = {
				fraudulent: __(
					'claiming this transaction was unauthorized',
					'woocommerce-payments'
				),
				product_not_received: __(
					'claiming they did not receive the product',
					'woocommerce-payments'
				),
				product_unacceptable: __(
					'claiming the product was unacceptable',
					'woocommerce-payments'
				),
				duplicate: __(
					'claiming this transaction was duplicated',
					'woocommerce-payments'
				),
			};

			const reasonClause =
				klarnaReasonClauses[ dispute.reason ] ??
				__( 'regarding this transaction', 'woocommerce-payments' );

			/* translators: %1$s is the reason clause, eg "claiming this transaction was unauthorized". %2$s is the deadline date. */
			noticeText = sprintf(
				__(
					'<strong>The customer has filed an inquiry through Klarna, %1$s.</strong> You can resolve this by working it out with the customer directly or issuing a refund. If unresolved by %2$s, the inquiry may escalate to a dispute, which you can challenge with evidence.',
					'woocommerce-payments'
				),
				reasonClause,
				dueByDate
			);
		}
	}
	// Handle regular inquiries
	else if ( isInquiry( dispute.status ) ) {
		/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unauthorized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						"<strong>%1$s</strong> If you believe this is incorrect, you have until <strong>%2$s to submit evidence to your customer's bank, %3$s.</strong> Alternatively, you can issue a refund.",
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate,
					bankName
			  )
			: sprintf(
					__(
						"<strong>%1$s</strong> If you believe this is incorrect, you have until <strong>%2$s to submit evidence to your customer's bank.</strong> Alternatively, you can issue a refund.",
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate
			  );
	}
	// Handle specific dispute reasons
	else if ( dispute.reason === 'noncompliant' ) {
		/* translators: %1$s is the bank name, eg "Chase Bank". %2$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						'Your customer’s bank, %1$s, claims this payment violates Visa’s rules. <strong>You can challenge the dispute by %2$s, or accept it.</strong> If you accept the dispute, you will forfeit the funds and pay the dispute fee. Challenging adds an additional $500 USD dispute fee that is only returned to you if you win.',
						'woocommerce-payments'
					),
					bankName,
					dueByDate
			  )
			: sprintf(
					__(
						'Your customer’s bank claims this payment violates Visa’s rules. <strong>You can challenge the dispute by %1$s, or accept it.</strong> If you accept the dispute, you will forfeit the funds and pay the dispute fee. Challenging adds an additional $500 USD dispute fee that is only returned to you if you win.',
						'woocommerce-payments'
					),
					dueByDate
			  );
	}
	// General case for disputes
	else {
		/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unauthorized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						"<strong>%1$s</strong> If you believe this is incorrect, you have until <strong>%2$s to challenge the dispute with your customer's bank, %3$s.</strong> If you accept the dispute, you will forfeit the funds and pay the dispute fee.",
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate,
					bankName
			  )
			: sprintf(
					__(
						"<strong>%1$s</strong> If you believe this is incorrect, you have until <strong>%2$s to challenge the dispute with your customer's bank.</strong> If you accept the dispute, you will forfeit the funds and pay the dispute fee.",
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate
			  );
	}

	return (
		<InlineNotice
			icon
			status={ isUrgent ? 'error' : 'warning' }
			className="dispute-notice"
			isDismissible={ false }
		>
			{ createInterpolateElement( noticeText, noticeElements ) }
		</InlineNotice>
	);
};

export default DisputeNotice;
