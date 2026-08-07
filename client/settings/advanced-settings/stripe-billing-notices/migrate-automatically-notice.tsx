/**
 * External dependencies
 */
import React, { useContext } from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './context';

interface Props {
	/**
	 * The number of subscriptions that will be automatically migrated.
	 */
	stripeBillingSubscriptionCount: number;
}

const MigrateAutomaticallyNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
} ) => {
	const context = useContext( StripeBillingMigrationNoticeContext );

	// The notice is eligible whenever the last-saved Stripe Billing snapshot is
	// enabled. Because the parent seeds the snapshot from the mount value, this
	// also covers the "Stripe Billing enabled on load" case before any save.
	if ( ! context.savedIsStripeBillingEnabled ) {
		return null;
	}

	// Don't show the notice if the migration option is shown.
	if ( context.isMigrationOptionShown ) {
		return null;
	}

	// Don't show the notice if there are no Stripe Billing subscriptions to migrate.
	if ( stripeBillingSubscriptionCount === 0 ) {
		return null;
	}

	if ( context.isStripeBillingEnabled ) {
		return null;
	}

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
						// @ts-expect-error: children is provided when interpolating the component
						<ExternalLink href="https://woocommerce.com/document/woopayments/subscriptions/stripe-billing/#disabling" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default MigrateAutomaticallyNotice;
