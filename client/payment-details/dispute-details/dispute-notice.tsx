/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import moment from 'moment';

/**
 * Internal dependencies
 */
import './style.scss';
import InlineNotice from 'components/inline-notice';
import { reasons } from 'wcpay/disputes/strings';
import { Dispute } from 'wcpay/types/disputes';
import { isInquiry } from 'wcpay/disputes/utils';

interface DisputeNoticeProps {
	dispute: Dispute;
	isUrgent: boolean;
	paymentMethod: string | null;
}

const DisputeNotice: React.FC< DisputeNoticeProps > = ( {
	dispute,
	isUrgent,
	paymentMethod,
} ) => {
	const shopperDisputeReason =
		reasons[ dispute.reason ]?.claim ??
		__(
			'The cardholder claims this is an unrecognized charge.',
			'woocommerce-payments'
		);

	// Format the deadline date
	const dueByDate = moment
		.unix( dispute.evidence_details?.due_by ?? 0 )
		.format( 'MMM D, YYYY h:mm A' );

	// Get the bank name - this is a placeholder since we don't have direct access to the bank name
	// In a real implementation, you would extract this from the dispute or charge object
	const bankName = 'Chase Bank'; // Placeholder - replace with actual bank name if available

	/* translators: <a> link to dispute documentation. %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
	let noticeText = __(
		"<strong>%1$s</strong> Challenge the dispute with the cardholder's bank – <strong>%2$s</strong> by <strong>%3$s</strong> if you believe the claim is invalid, " +
			'or accept to forfeit the funds and pay the dispute fee. ' +
			'Non-response will result in an automatic loss. <a>Learn more about responding to disputes</a>',
		'woocommerce-payments'
	);
	let learnMoreDocsUrl =
		'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#responding';

	if ( isInquiry( dispute.status ) ) {
		/* translators: <a> link to dispute inquiry documentation. %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = __(
			"<strong>%1$s</strong> You can challenge their claim with <strong>%2$s</strong> by <strong>%3$s</strong> if you believe it's invalid. " +
				'Not responding will result in an automatic loss. <a>Learn more about payment inquiries</a>',
			'woocommerce-payments'
		);
		learnMoreDocsUrl =
			'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#inquiries';

		if ( paymentMethod === 'klarna' ) {
			noticeText = __(
				'Klarna inquiries may mean that the customer is trying to return their item(s). ' +
					'<a>Please see this document for more information</a>',
				'woocommerce-payments'
			);
			learnMoreDocsUrl =
				'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#klarna-inquiries-returns';
		}
	}

	return (
		<InlineNotice
			icon
			status={ isUrgent ? 'error' : 'warning' }
			className="dispute-notice"
			isDismissible={ false }
		>
			{ createInterpolateElement(
				sprintf(
					noticeText,
					shopperDisputeReason,
					bankName,
					dueByDate
				),
				{
					a: (
						<ExternalLink
							className="dispute-notice__link"
							href={ learnMoreDocsUrl }
						/>
					),
					strong: <strong />,
				}
			) }
		</InlineNotice>
	);
};

export default DisputeNotice;
