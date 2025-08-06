/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { Icon, Button } from '@wordpress/components';
import { envelope, comment, page } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { ChargeBillingDetails } from 'wcpay/types/charges';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import InlineNotice from 'components/inline-notice';
import {
	Accordion,
	AccordionBody,
	AccordionRow,
} from 'wcpay/components/accordion';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
	bankName: string | null;
}

export const DisputeSteps: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	bankName,
} ) => {
	let emailLink;
	if ( customer?.email ) {
		const chargeDate = formatDateTimeFromTimestamp( chargeCreated );
		const disputeDate = formatDateTimeFromTimestamp( dispute.created );
		const emailSubject = sprintf(
			// Translators: %1$s is the store name, %2$s is the charge date.
			__(
				`Problem with your purchase from %1$s on %2$s?`,
				'woocommerce-payments'
			),
			wcpaySettings.storeName,
			chargeDate
		);
		const customerName = customer?.name || '';
		const emailBody = sprintf(
			// Translators: %1$s is the customer name, %2$s is the dispute date, %3$s is the dispute amount with currency-code e.g. $15 USD, %4$s is the charge date.
			__(
				`Hello %1$s,\n\n` +
					`We noticed that on %2$s, you disputed a %3$s charge on %4$s. We wanted to contact you to make sure everything was all right with your purchase and see if there's anything else we can do to resolve any problems you might have had.\n\n` +
					`Alternatively, if the dispute was a mistake, you can easily withdraw it by calling the number on the back of your card. Thank you so much - we appreciate your business and look forward to working with you.`,
				'woocommerce-payments'
			),
			customerName,
			disputeDate,
			formatExplicitCurrency( dispute.amount, dispute.currency ),
			chargeDate
		);
		emailLink = `mailto:${ customer.email }?subject=${ encodeURIComponent(
			emailSubject
		) }&body=${ encodeURIComponent( emailBody ) }`;
	}

	return (
		<div className="dispute-steps">
			<Accordion>
				<AccordionBody
					lg
					title="Steps you can take"
					subtitle="We recommend reviewing your options before responding by the deadline. "
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Contact your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Contact your customer',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												'Identify the issue and work towards a resolution where possible.',
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										{ customer?.email ? (
											<Button
												variant="secondary"
												href={ emailLink }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __(
													'Email customer',
													'woocommerce-payments'
												) }
											</Button>
										) : null }
									</div>
								</div>

								{ /* Step 2: Ask for the dispute to be withdrawn */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ comment } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Ask for the dispute to be withdrawn',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"If you've managed to resolve the issue with your customer, help them with the withdrawal of their dispute.",
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										<Button
											variant="secondary"
											href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#withdrawals"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</Button>
									</div>
								</div>

								{ /* Step 3: Challenge or accept the dispute */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ page } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Challenge or accept the dispute',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"Disagree with the dispute? You can challenge it with the customer's bank. Otherwise, accept it to close the case — the order amount and dispute fee won't be refunded.",
												'woocommerce-payments'
											) }
										</div>
									</div>
								</div>
							</div>

							{ /* Dispute notice */ }
							<div className="dispute-steps__notice">
								<InlineNotice
									icon
									isDismissible={ false }
									status="info"
									className="dispute-steps__notice-content"
								>
									{ createInterpolateElement(
										bankName
											? sprintf(
													__(
														'<strong>The outcome of this dispute will be determined by %1$s.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>The outcome of this dispute will be determined by the cardholder's bank.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.",
													'woocommerce-payments'
											  ),
										{
											strong: <strong />,
										}
									) }
								</InlineNotice>
							</div>
						</div>
					</AccordionRow>
				</AccordionBody>
			</Accordion>
		</div>
	);
};

export const InquirySteps: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	bankName,
} ) => {
	let emailLink;
	if ( customer?.email ) {
		const chargeDate = formatDateTimeFromTimestamp( chargeCreated, {
			includeTime: true,
		} );
		const disputeDate = formatDateTimeFromTimestamp( dispute.created, {
			includeTime: true,
		} );
		const emailSubject = sprintf(
			// Translators: %1$s is the store name, %2$s is the charge date.
			__(
				`Problem with your purchase from %1$s on %2$s?`,
				'woocommerce-payments'
			),
			wcpaySettings.storeName,
			chargeDate
		);
		const customerName = customer?.name || '';
		const emailBody = sprintf(
			// Translators: %1$s is the customer name, %2$s is the dispute date, %3$s is the dispute amount with currency-code e.g. $15 USD, %4$s is the charge date.
			__(
				`Hello %1$s,\n\n` +
					`We noticed that on %2$s, you raised a question with your payment provider about a %3$s charge made on %4$s. We wanted to reach out to ensure everything is all right with your purchase and to see if there's anything we can do to resolve any problems you might have had.\n\n` +
					`Alternatively, if this was a mistake, please contact your payment provider to resolve it. Thank you so much - we appreciate your business and look forward to working with you.`,
				'woocommerce-payments'
			),
			customerName,
			disputeDate,
			formatExplicitCurrency( dispute.amount, dispute.currency ),
			chargeDate
		);
		emailLink = `mailto:${ customer.email }?subject=${ encodeURIComponent(
			emailSubject
		) }&body=${ encodeURIComponent( emailBody ) }`;
	}

	return (
		<div className="dispute-steps">
			<Accordion>
				<AccordionBody
					lg
					title="Steps you can take"
					subtitle="We recommend reviewing your options before responding by the deadline. "
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Contact your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Contact your customer',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												'Identify the issue and work towards a resolution where possible.',
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										{ customer?.email ? (
											<Button
												variant="secondary"
												href={ emailLink }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __(
													'Email customer',
													'woocommerce-payments'
												) }
											</Button>
										) : null }
									</div>
								</div>

								{ /* Step 2: Provide guidance for inquiry withdrawal */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ page } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Submit evidence or issue a refund',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"Disagree with the claim? You can challenge it by submitting evidence to the customer's bank. Otherwise, you can settle the inquiry by issuing a refund.",
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										<Button
											variant="secondary"
											href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#inquiries"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</Button>
									</div>
								</div>
							</div>

							{ /* Dispute notice */ }
							<div className="dispute-steps__notice">
								<InlineNotice
									icon
									isDismissible={ false }
									status="info"
									className="dispute-steps__notice-content"
								>
									{ createInterpolateElement(
										bankName
											? sprintf(
													__(
														'<strong>The outcome of this dispute will be determined by %1$s.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>The outcome of this dispute will be determined by the cardholder's bank.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.",
													'woocommerce-payments'
											  ),
										{
											strong: <strong />,
										}
									) }
								</InlineNotice>
							</div>
						</div>
					</AccordionRow>
				</AccordionBody>
			</Accordion>
		</div>
	);
};

export const NotDefendableInquirySteps: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	bankName,
} ) => {
	let emailLink;
	if ( customer?.email ) {
		const chargeDate = formatDateTimeFromTimestamp( chargeCreated, {
			includeTime: true,
		} );
		const disputeDate = formatDateTimeFromTimestamp( dispute.created, {
			includeTime: true,
		} );
		const emailSubject = sprintf(
			// Translators: %1$s is the store name, %2$s is the charge date.
			__(
				`Problem with your purchase from %1$s on %2$s?`,
				'woocommerce-payments'
			),
			wcpaySettings.storeName,
			chargeDate
		);
		const customerName = customer?.name || '';
		const emailBody = sprintf(
			// Translators: %1$s is the customer name, %2$s is the dispute date, %3$s is the dispute amount with currency-code e.g. $15 USD, %4$s is the charge date.
			__(
				`Hello %1$s,\n\n` +
					`We noticed that on %2$s, you raised a question with your payment provider about a %3$s charge made on %4$s. We wanted to reach out to ensure everything is all right with your purchase and to see if there's anything we can do to resolve any problems you might have had.\n\n` +
					`Alternatively, if this was a mistake, please contact your payment provider to resolve it. Thank you so much - we appreciate your business and look forward to working with you.`,
				'woocommerce-payments'
			),
			customerName,
			disputeDate,
			formatExplicitCurrency( dispute.amount, dispute.currency ),
			chargeDate
		);
		emailLink = `mailto:${ customer.email }?subject=${ encodeURIComponent(
			emailSubject
		) }&body=${ encodeURIComponent( emailBody ) }`;
	}

	return (
		<div className="dispute-steps">
			<Accordion>
				<AccordionBody
					lg
					title="Steps you can take"
					subtitle="We recommend reviewing your options before responding by the deadline. "
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Contact your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Contact your customer',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"Reach out to the customer to check if they're returning the item(s).",
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										{ customer?.email ? (
											<Button
												variant="secondary"
												href={ emailLink }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __(
													'Email customer',
													'woocommerce-payments'
												) }
											</Button>
										) : null }
									</div>
								</div>

								{ /* Step 2: Issue refund */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ page } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Issue a refund',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"Once you've received the item(s), refund the customer before the deadline to prevent this escalating to a dispute.",
												'woocommerce-payments'
											) }
										</div>
									</div>
								</div>

								{ /* Step 3: Challenge the dispute if the item is not returned */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Challenge the dispute',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												"Didn't receive the returned item(s)? Once the inquiry has automatically escalated to a dispute after 21 days, you can submit evidence and challenge the dispute.",
												'woocommerce-payments'
											) }
										</div>
									</div>
									<div className="dispute-steps__item-action">
										<Button
											variant="secondary"
											href="https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#klarna-inquiries-returns"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</Button>
									</div>
								</div>
							</div>

							{ /* Dispute notice */ }
							<div className="dispute-steps__notice">
								<InlineNotice
									icon
									isDismissible={ false }
									status="info"
									className="dispute-steps__notice-content"
								>
									{ createInterpolateElement(
										bankName
											? sprintf(
													__(
														'<strong>The outcome of this dispute will be determined by %1$s.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>The outcome of this dispute will be determined by the cardholder's bank.</strong> WooPayments has no influence over the decision and is not liable for any chargebacks.",
													'woocommerce-payments'
											  ),
										{
											strong: <strong />,
										}
									) }
								</InlineNotice>
							</div>
						</div>
					</AccordionRow>
				</AccordionBody>
			</Accordion>
		</div>
	);
};
