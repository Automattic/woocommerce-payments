/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

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
			separator: ' ',
			includeTime: true,
		}
	);

	// Determine the appropriate notice text based on dispute type and reason
	let noticeText = '';

	// Handle Klarna inquiries specifically
	if ( paymentMethod === 'klarna' && isInquiry( dispute.status ) ) {
		noticeText = __(
			'Klarna inquiries may mean that the customer is trying to return their item(s).',
			'woocommerce-payments'
		);
	}
	// Handle regular inquiries
	else if ( isInquiry( dispute.status ) ) {
		/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unauthorized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						'<strong>%1$s</strong> Submit the evidence to <strong>%2$s</strong> by <strong>%3$s</strong> if you believe the claim to be invalid, or issue a refund.',
						'woocommerce-payments'
					),
					shopperDisputeReason,
					bankName,
					dueByDate
			  )
			: sprintf(
					__(
						"<strong>%1$s</strong> Submit the evidence to <strong>Cardholder's bank</strong> by <strong>%2$s</strong> if you believe the claim to be invalid, or issue a refund.",
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate
			  );
	}
	// Handle specific dispute reasons
	else {
		/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unauthorized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you believe the claim to be invalid, or accept to forfeit the funds and pay the dispute fee.',
						'woocommerce-payments'
					),
					shopperDisputeReason,
					bankName,
					dueByDate
			  )
			: sprintf(
					__(
						"<strong>%1$s</strong> Challenge the dispute with <strong>Cardholder's bank</strong> by <strong>%2$s</strong> if you believe the claim to be invalid, or accept to forfeit the funds and pay the dispute fee.",
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
			{ createInterpolateElement( noticeText, {
				strong: <strong />,
			} ) }
		</InlineNotice>
	);
};

export default DisputeNotice;
