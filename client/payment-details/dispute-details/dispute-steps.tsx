/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type { Dispute } from 'wcpay/types/disputes';
import { ChargeBillingDetails } from 'wcpay/types/charges';
import { formatExplicitCurrency } from 'utils/currency';

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
			`We noticed that on ${ disputeDate }, you disputed a ${ formatExplicitCurrency(
				dispute.amount,
				dispute.currency
			) } from ${ chargeDate }. We wanted to contact you to make sure everything was all right with your purchase and see if there's anything else we can do to resolve any problems you might have had.\n\n` +
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
					{ __(
						'Challenge or accept the dispute by',
						'woocommerce-payments'
					) }
				</li>
			</ol>
		</div>
	);
};

export default DisputeSteps;
