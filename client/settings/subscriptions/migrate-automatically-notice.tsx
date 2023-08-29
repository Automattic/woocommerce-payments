/** @format */
/**
 * External dependencies
 */
import React from 'react';
import BannerNotice from 'wcpay/components/banner-notice';
import { _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

interface Props {
	/**
	 * The number of subscriptions that are being migrated.
	 */
	stripeBillingSubscriptionCount: number;
}

const MigrateAutomaticallyNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
} ) => {
	return (
		<BannerNotice
			status="warning"
			isDismissible={ false }
			className="woopayments-stripe-billing-notice"
		>
			{ interpolateComponents( {
				mixedString: sprintf(
					_n(
						'There is currently %d customer subscriptions using Stripe Billing for payment processing.' +
							' This subscription will be automatically migrated once Stripe Billing is disabled.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'There are currently %d customer subscriptions using Stripe Billing for payment processing.' +
							' These subscriptions will be automatically migrated once Stripe Billing is disabled.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						stripeBillingSubscriptionCount,
						'woocommerce-payments'
					),
					stripeBillingSubscriptionCount
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
					),
				},
			} ) }
		</BannerNotice>
	);
};

export default MigrateAutomaticallyNotice;
