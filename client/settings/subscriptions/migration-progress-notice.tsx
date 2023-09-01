/** @format */
/**
 * External dependencies
 */
import React from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { _n, sprintf } from '@wordpress/i18n';

interface Props {
	/**
	 * The number of subscriptions that are being migrated.
	 */
	stripeBillingSubscriptionCount: number;

	/**
	 * The function to call when the notice is dismissed.
	 */
	onRemove: () => void;
}

const MigrationInProgressNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
	onRemove,
} ) => {
	return (
		<InlineNotice
			status="info"
			isDismissible={ true }
			onRemove={ onRemove }
			className="woopayments-stripe-billing-notice"
		>
			{ sprintf(
				_n(
					'%d customer subscription is being migrated from Stripe off-site billing to billing powered by' +
						' %s and %s.',
					'%d customer subscriptions are being migrated from Stripe off-site billing to billing powered by' +
						' %s and %s.',
					stripeBillingSubscriptionCount,
					'woocommerce-payments'
				),
				stripeBillingSubscriptionCount,
				'Woo Subscriptions',
				'WooPayments'
			) }
		</InlineNotice>
	);
};

export default MigrationInProgressNotice;
