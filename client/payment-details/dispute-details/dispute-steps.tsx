/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { Icon, Button } from 'wcpay/components/wp-components-wrapped';
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
					subtitle="Review these steps you can take to respond to disputes effectively"
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Reach out to your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Reach out to your customer',
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

								{ /* Step 2: Pursue a dispute withdrawal */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ comment } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Pursue a dispute withdrawal',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												'See if the customer will withdraw their dispute.',
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
												'Challenge the dispute if you consider the claim to be invalid. Accepting the dispute will automatically close it and the order amount and the dispute fee will not be returned to you.',
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
														'<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. <strong>%1$s</strong> makes the decision in this process.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. The cardholder's bank makes the decision in this process.",
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
					subtitle="Review these steps you can take to respond to disputes effectively"
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Reach out to your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Reach out to your customer',
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
												'Submit the evidence by providing the requested information.',
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
														'<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. <strong>%1$s</strong> makes the decision in this process.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. The cardholder's bank makes the decision in this process.",
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
					subtitle="Review these steps you can take to respond to disputes effectively"
				>
					<AccordionRow>
						<div className="dispute-steps__content">
							<div className="dispute-steps__items">
								{ /* Step 1: Reach out to your customer */ }
								<div className="dispute-steps__item">
									<div className="dispute-steps__item-icon">
										<Icon icon={ envelope } />
									</div>
									<div className="dispute-steps__item-content">
										<div className="dispute-steps__item-name">
											{ __(
												'Reach out to your customer',
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
												'Issue a refund if the item is returned.',
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
												'Challenge the dispute if the item is not returned',
												'woocommerce-payments'
											) }
										</div>
										<div className="dispute-steps__item-description">
											{ __(
												'Allow this inquiry to become a dispute in 21 days if you don’t receive the item.',
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
														'<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. <strong>%1$s</strong> makes the decision in this process.',
														'woocommerce-payments'
													),
													bankName
											  )
											: __(
													"<strong>WooPayments does not determine the outcome of the dispute process</strong> and is not liable for any chargebacks. The cardholder's bank makes the decision in this process.",
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
