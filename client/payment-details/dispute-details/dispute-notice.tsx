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
import { PAYMENT_METHOD_BRANDS } from 'wcpay/constants/payment-method';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';

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
	const dueByDate = formatDateTimeFromTimestamp(
		dispute.evidence_details?.due_by ?? 0
	);

	// Get the bank name from payment method details
	const getBankName = () => {
		// If the charge is a string, it means it's a charge ID, so we can't get the bank name
		if ( typeof dispute.charge === 'string' ) {
			return null;
		}

		const { payment_method_details: paymentMethodDetails } = dispute.charge;
		const methodType = paymentMethod?.toLowerCase();

		if ( methodType === 'giropay' && 'giropay' in paymentMethodDetails ) {
			return paymentMethodDetails.giropay.bank_name;
		}
		if (
			methodType === 'bancontact' &&
			'bancontact' in paymentMethodDetails
		) {
			return paymentMethodDetails.bancontact.bank_name;
		}
		if ( methodType === 'sofort' && 'sofort' in paymentMethodDetails ) {
			return paymentMethodDetails.sofort.bank_name;
		}
		if ( methodType === 'card' && 'card' in paymentMethodDetails ) {
			const { brand } = paymentMethodDetails.card;
			return brand && typeof brand === 'string'
				? PAYMENT_METHOD_BRANDS[
						brand.toUpperCase() as keyof typeof PAYMENT_METHOD_BRANDS
				  ]
				: null;
		}

		return null;
	};

	const bankName = getBankName();

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
		/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
		noticeText = bankName
			? sprintf(
					__(
						'<strong>%1$s</strong> Submit the evidence with the cardholder’s bank – <strong>%2$s</strong> <strong>(You have %3$s to respond)</strong>. ' +
							'Not responding will result in an automatic loss.',
						'woocommerce-payments'
					),
					shopperDisputeReason,
					bankName,
					dueByDate
			  )
			: sprintf(
					__(
						'<strong>%1$s</strong> Submit the evidence with the cardholder’s bank <strong>(You have %2$s to respond)</strong>. ' +
							'Not responding will result in an automatic loss.',
						'woocommerce-payments'
					),
					shopperDisputeReason,
					dueByDate
			  );
	}
	// Handle specific dispute reasons
	else {
		switch ( dispute.reason ) {
			case 'product_not_received':
				noticeText = bankName
					? sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you can prove delivery. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute by <strong>%3$s</strong> if you can prove delivery. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
				break;
			case 'product_unacceptable':
				noticeText = bankName
					? sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you can prove the product was as described. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute by <strong>%3$s</strong> if you can prove the product was as described. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
				break;
			case 'fraudulent':
				noticeText = bankName
					? sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you can prove the transaction was authorized. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute by <strong>%3$s</strong> if you can prove the transaction was authorized. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
				break;
			case 'duplicate':
				noticeText = bankName
					? sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you can prove this is not a duplicate charge. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute by <strong>%3$s</strong> if you can prove this is not a duplicate charge. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
				break;
			case 'subscription_canceled':
				noticeText = bankName
					? sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute with <strong>%2$s</strong> by <strong>%3$s</strong> if you can prove the subscription was not canceled. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								'<strong>%1$s</strong> Challenge the dispute by <strong>%3$s</strong> if you can prove the subscription was not canceled. ' +
									'Otherwise, accept the dispute and refund the customer. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
				break;
			default:
				/* translators: %1$s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." %2$s is the bank name, eg "Chase Bank". %3$s is the deadline date, eg "Aug 18, 2023 11:59 PM". */
				noticeText = bankName
					? sprintf(
							__(
								"<strong>%1$s</strong> Challenge the dispute with the cardholder's bank – <strong>%2$s</strong> by <strong>%3$s</strong> if you believe the claim is invalid, " +
									'or accept to forfeit the funds and pay the dispute fee. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							bankName,
							dueByDate
					  )
					: sprintf(
							__(
								"<strong>%1$s</strong> Challenge the dispute with the cardholder's bank by <strong>%3$s</strong> if you believe the claim is invalid, " +
									'or accept to forfeit the funds and pay the dispute fee. ' +
									'Non-response will result in an automatic loss.',
								'woocommerce-payments'
							),
							shopperDisputeReason,
							dueByDate
					  );
		}
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
