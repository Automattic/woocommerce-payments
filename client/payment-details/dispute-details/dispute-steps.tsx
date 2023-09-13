/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { ChargeBillingDetails } from 'wcpay/types/charges';
import { formatExplicitCurrency } from 'utils/currency';
import { ClickTooltip } from 'wcpay/components/tooltip';

interface Props {
	dispute: Dispute;
	customer: ChargeBillingDetails | null;
	chargeCreated: number;
}

const DisputeSteps: React.FC< Props > = ( {
	dispute,
	customer,
	chargeCreated,
} ) => {
	const formattedDisputeAmount = formatExplicitCurrency(
		dispute.amount,
		dispute.currency
	);

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
		const emailSubject = `Problem with your purchase from ${ wcpaySettings.storeName } on ${ chargeDate }?`;
		const emailBody =
			`Hello ${ customer?.name }\n\n` +
			`We noticed that on ${ disputeDate }, you disputed a ${ formattedDisputeAmount } from ${ chargeDate }. We wanted to contact you to make sure everything was all right with your purchase and see if there's anything else we can do to resolve any problems you might have had.\n\n` +
			`Alternatively, if the dispute was a mistake, you could easily withdraw it by calling the number on the back of your card. Thank you so much - we appreciate your business and look forward to working with you.`;

		emailLink = `mailto:${ customer.email }?subject=${ encodeURIComponent(
			emailSubject
		) }&body=${ encodeURIComponent( emailBody ) }`;
	}

	return (
		<div className="dispute-steps">
			<div className="dispute-steps__header">
				{ __( 'Steps to resolve:', 'woocommercts' ) }
			</div>
			<ol className="dispute-steps__steps">
				<li>
					{ __(
						"Review the claim issued by the cardholder's bank.",
						'woocommerce-payments'
					) }
				</li>
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
					{ __(
						'Provide guidance on dispute withdrawal if the customer agrees.',
						'woocommerce-payments'
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
									className="abc"
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
									className="def"
									buttonIcon={ <HelpOutlineIcon /> }
									buttonLabel={ __(
										'Accept the dispute',
										'woocommerce-payments'
									) }
									content={ sprintf(
										__(
											`Accepting this dispute will automatically close it. Your account will be charged a %s fee, and the disputed amount will be refunded to the cardholder.`,
											'woocommerce-payments'
										),
										formattedDisputeAmount
									) }
								/>
							),
							disputeduedate: <></>,
						}
					) }
				</li>
			</ol>
		</div>
	);
};

export default DisputeSteps;
