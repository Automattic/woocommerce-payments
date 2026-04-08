/**
 * External dependencies
 */
import React, { useState, useContext, useEffect } from 'react';
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

	/**
	 * Whether the notice is eligible to be shown.
	 *
	 * Note: We use `useState` here to snapshot the setting value on load.
	 *
	 * This notice should only be shown if a migration is in progress.
	 * The migration is in progress if the settings have been saved and Stripe Billing is disabled or if the migration option is clicked.
	 */
	const [ isEligible, setIsEligible ] = useState(
		context.isMigrationInProgress
	);

	// Set the notice to be eligible if the user has chosen to migrate.
	useEffect( () => {
		if ( context.hasResolvedMigrateRequest ) {
			setIsEligible( true );
		}
	}, [ context.hasResolvedMigrateRequest ] );

	// Set the notice to be eligible if Stripe Billing is saved as disabled. When disabling Stripe Billing, the migration will automatically start.
	useEffect( () => {
		if ( context.hasSavedSettings ) {
			setIsEligible( ! context.savedIsStripeBillingEnabled );
		}
	}, [ context.hasSavedSettings, context.savedIsStripeBillingEnabled ] );

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

	// Mark the notice as shown.
	context.isMigrationInProgressShown = true;

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
