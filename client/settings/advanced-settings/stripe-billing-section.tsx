/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useStripeBilling, useStripeBillingMigration } from 'wcpay/data';
import Notices from './stripe-billing-notices/notices';
import StripeBillingMigrationNoticeContext from './stripe-billing-notices/context';
import StripeBillingToggle from './stripe-billing-toggle';
import { StripeBillingHook, StripeBillingMigrationHook } from './interfaces';

/**
 * Renders a WooPayments subscriptions settings card.
 *
 * @return {JSX.Element} Rendered subscriptions element.
 */
const StripeBillingSection: React.FC = () => {
	const [
		isStripeBillingEnabled,
		updateIsStripeBillingEnabled,
	] = useStripeBilling() as StripeBillingHook;
	const [
		isMigrationInProcess,
		migratedCount,
		subscriptionCount,
		startMigration,
		isResolving,
		hasResolved,
	] = useStripeBillingMigration() as StripeBillingMigrationHook;

	// Set up the context to be shared between the notices and the toggle.
	const [ isMigrationInProgress ] = useState( false );
	const [ isMigrationOptionShown ] = useState( false );

	const noticeContext = {
		isStripeBillingEnabled: isStripeBillingEnabled,

		// Notice logic.
		isMigrationOptionShown: isMigrationOptionShown,
		isMigrationInProgressShown: isMigrationInProgress,

		// Migration logic & counts.
		isMigrationInProgress: isMigrationInProcess,
		subscriptionCount: subscriptionCount,
		migratedCount: migratedCount,

		// Migration actions & state.
		startMigration: startMigration,
		isResolving: isResolving,
		hasResolved: hasResolved,
	};

	return (
		<StripeBillingMigrationNoticeContext.Provider value={ noticeContext }>
			<h4>{ __( 'Subscriptions', 'woocommerce-payments' ) }</h4>
			<Notices />
			<StripeBillingToggle onChange={ updateIsStripeBillingEnabled } />
		</StripeBillingMigrationNoticeContext.Provider>
	);
};

export default StripeBillingSection;
