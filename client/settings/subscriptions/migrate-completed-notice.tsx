/** @format */
/**
 * External dependencies
 */
import React from 'react';
import BannerNotice from 'wcpay/components/banner-notice';
import { _n, sprintf } from '@wordpress/i18n';

interface Props {
	/**
	 * The number of subscriptions that have been migrated.
	 */
	completedMigrationCount: number;

	/**
	 * The function to call when the notice is dismissed.
	 */
	onRemove: () => void;
}

const MigrationCompletedNotice: React.FC< Props > = ( {
	completedMigrationCount,
	onRemove,
} ) => {
	return (
		<BannerNotice
			status="info"
			isDismissible={ true }
			onRemove={ onRemove }
			className="woopayments-stripe-billing-notice"
		>
			{ sprintf(
				_n(
					'%d customer subscription was successfully migrated from Stripe off-site billing to on-site billing' +
						' powered by %s and %s.',
					'%d customer subscriptions were successfully migrated from Stripe off-site billing to on-site billing' +
						' powered by %s and %s.',
					completedMigrationCount,
					'woocommerce-payments'
				),
				completedMigrationCount,
				'Woo Subscriptions',
				'WooPayments'
			) }
		</BannerNotice>
	);
};

export default MigrationCompletedNotice;
