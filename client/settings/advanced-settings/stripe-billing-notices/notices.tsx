/**
 * External dependencies
 */
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import { useSettings } from 'wcpay/data';
import StripeBillingMigrationNoticeContext from './context';
import MigrationInProgressNotice from './migration-progress-notice';
import MigrateOptionNotice from './migrate-option-notice';
import MigrateAutomaticallyNotice from './migrate-automatically-notice';
import MigrationCompletedNotice from './migrate-completed-notice';

/**
 * Renders a WooPayments subscriptions settings card.
 *
 * @return {JSX.Element} Rendered subscriptions element.
 */
const Notices: React.FC = () => {
	const { isLoading } = useSettings();
	const context = useContext( StripeBillingMigrationNoticeContext );

	if ( isLoading ) {
		return null;
	}

	return (
		<>
			<MigrateOptionNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
				startMigration={ () => {
					context.startMigration();
				} }
				isLoading={ context.isResolving }
				hasResolved={ context.hasResolved }
			/>
			<MigrateAutomaticallyNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
			/>
			<MigrationInProgressNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
			/>
			<MigrationCompletedNotice
				completedMigrationCount={ context.migratedCount }
			/>
		</>
	);
};

export default Notices;
