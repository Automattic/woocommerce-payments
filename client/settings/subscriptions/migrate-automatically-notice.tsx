/** @format */
/**
 * External dependencies
 */
import React from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

interface Props {
	/**
	 * The number of subscriptions that will be automatically migrated.
	 */
	stripeBillingSubscriptionCount: number;
}

const MigrateAutomaticallyNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
} ) => {
	return (
		<InlineNotice
			status="warning"
			isDismissible={ false }
			className="woopayments-stripe-billing-notice"
		>
			{ interpolateComponents( {
				mixedString: sprintf(
					_n(
						'There is currently %d customer subscription using Stripe Billing for payment processing.' +
							' This subscription will be automatically migrated to use the on-site billing engine' +
							' built into %s once Stripe Billing is disabled.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'There are currently %d customer subscriptions using Stripe Billing for payment processing.' +
							' These subscriptions will be automatically migrated to use the on-site billing engine' +
							' built into %s once Stripe Billing is disabled.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						stripeBillingSubscriptionCount,
						'woocommerce-payments'
					),
					stripeBillingSubscriptionCount,
					'Woo Subscriptions'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default MigrateAutomaticallyNotice;
