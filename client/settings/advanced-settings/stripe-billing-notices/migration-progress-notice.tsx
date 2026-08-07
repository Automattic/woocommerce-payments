/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './context';

interface Props {
	/**
	 * The number of subscriptions that are being migrated.
	 */
	stripeBillingSubscriptionCount: number;
}

const MigrationInProgressNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
} ) => {
	const [ isDismissed, setIsDismissed ] = useState( false );

	const context = useContext( StripeBillingMigrationNoticeContext );

	// The notice is eligible when a migration is happening for any reason:
	//   - the server already reports a migration in progress (initial load), or
	//   - the user's "Begin migration" request has resolved, or
	//   - the user has completed at least one save cycle and Stripe Billing
	//     ended up disabled (which triggers an automatic migration).
	const isEligible =
		context.isMigrationInProgress ||
		context.hasResolvedMigrateRequest ||
		( context.hasCompletedSave && ! context.savedIsStripeBillingEnabled );

	// Don't show the notice if it's not eligible.
	if ( ! isEligible ) {
		return null;
	}

	// Don't show the notice if it has been dismissed.
	if ( isDismissed ) {
		return null;
	}

	if ( context.subscriptionCount === 0 ) {
		return null;
	}

	// Don't show the notice if the migration option is shown.
	if ( context.isMigrationOptionShown ) {
		return null;
	}

	return (
		<InlineNotice
			status="info"
			isDismissible={ true }
			onRemove={ () => setIsDismissed( true ) }
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
