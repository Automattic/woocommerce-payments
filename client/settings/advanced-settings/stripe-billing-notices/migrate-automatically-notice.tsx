/** @format */
/**
 * External dependencies
 */
import React, { useState, useContext, useEffect } from 'react';
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

	/**
	 * Whether the notice is eligible to be shown.
	 *
	 * Note: We use `useState` here to snapshot the setting value on load.
	 * This notice should only be shown if Stripe Billing was enabled on load.
	 */
	const [ isEligible, setIsEligible ] = useState(
		context.isStripeBillingEnabled
	);

	// Set the notice to be eligible if the setting if Stripe Billing is saved as enabled.
	useEffect( () => {
		if ( context.hasSavedSettings ) {
			setIsEligible( context.savedIsStripeBillingEnabled );
		}
	}, [ context.hasSavedSettings, context.savedIsStripeBillingEnabled ] );

	if ( ! isEligible ) {
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
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default MigrateAutomaticallyNotice;
