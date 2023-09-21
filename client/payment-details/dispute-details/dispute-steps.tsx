/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { ExternalLink } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { ChargeBillingDetails } from 'wcpay/types/charges';
import { formatExplicitCurrency } from 'utils/currency';
import { ClickTooltip } from 'wcpay/components/tooltip';
import { getDisputeFeeFormatted } from 'wcpay/disputes/utils';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
	daysRemaining: number;
}

const DisputeSteps: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
	daysRemaining,
} ) => {
	let emailLink;
	if ( customer?.email ) {
		const chargeDate = dateI18n(
			'Y-m-d',
			moment( chargeCreated * 1000 ).toISOString()
		);
		const disputeDate = dateI18n(
			'Y-m-d',
			moment( dispute.created * 1000 ).toISOString()
		);
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
				`Hello %1$s\n\n` +
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

	const respondByDate = dispute.evidence_details?.due_by
		? dateI18n(
				'M j, Y, g:ia',
				moment( dispute.evidence_details?.due_by * 1000 ).toISOString()
		  )
		: '–';

	return (
		<div className="dispute-steps">
			<div className="dispute-steps__header">
				{ __( 'Steps to resolve:', 'woocommerce-payments' ) }
			</div>
			<ol className="dispute-steps__steps">
				<li>
					{ customer?.email
						? createInterpolateElement(
								__(
									'<a>Email the customer</a> to address their concerns.',
									'woocommerce-payments'
								),
								{
									a: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											target="_blank"
											rel="noopener noreferrer"
											href={ emailLink }
										/>
									),
								}
						  )
						: __(
								'Email the customer to address their concerns.',
								'woocommerce-payments'
						  ) }
				</li>
				<li>
					{ createInterpolateElement(
						__(
							'Provide <a>guidance on dispute withdrawal</a> if the customer agrees.',
							'woocommerce-payments'
						),
						{
							a: (
								<ExternalLink href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#withdrawals" />
							),
						}
					) }
				</li>
				<li>
					{ createInterpolateElement(
						__(
							'Challenge <challengeicon/> or accept <accepticon/> the dispute by <disputeduedate/>.',
							'woocommerce-payments'
						),
						{
							challengeicon: (
								<ClickTooltip
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Challenge the dispute',
										'woocommerce-payments'
									) }
									content={ __(
										"Challenge the dispute if you consider the claim invalid. You'll need to provide evidence to back your claim. Keep in mind that challenging doesn't ensure a resolution in your favor.",
										'woocommerce-payments'
									) }
								/>
							),
							accepticon: (
								<ClickTooltip
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Accept the dispute',
										'woocommerce-payments'
									) }
									content={ sprintf(
										// Translators: %s is a formatted currency amount, eg $10.00.
										__(
											`Accepting this dispute will automatically close it. Your account will be charged a %s fee, and the disputed amount will be refunded to the cardholder.`,
											'woocommerce-payments'
										),
										getDisputeFeeFormatted(
											dispute,
											true
										) || '-'
									) }
								/>
							),
							disputeduedate: (
								<span className="dispute-steps__steps__response-date">
									{ respondByDate }
									<span
										className={ classNames( {
											'dispute-steps__steps__response-date--urgent':
												daysRemaining < 3,
											'dispute-steps__steps__response-date--warning':
												daysRemaining < 7 &&
												daysRemaining > 2,
										} ) }
									>
										{ daysRemaining === 0
											? __(
													'(Last day today)',
													'woocommerce-payments'
											  )
											: sprintf(
													// Translators: %s is the number of days left to respond to the dispute.
													_n(
														'(%s day left to respond)',
														'(%s days left to respond)',
														daysRemaining,
														'woocommerce-payments'
													),
													daysRemaining
											  ) }
									</span>
								</span>
							),
						}
					) }
				</li>
			</ol>
		</div>
	);
};

export default DisputeSteps;
