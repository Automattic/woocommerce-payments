/**
 * External dependencies
 */
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './context';
import MigrationInProgressNotice from './migration-progress-notice';
import MigrateOptionNotice from './migrate-option-notice';
import MigrateAutomaticallyNotice from './migrate-automatically-notice';
import MigrationCompletedNotice from './migrate-completed-notice';
import './style.scss';

/**
 * Renders the Stripe Billing notices.
 *
 * @return {JSX.Element} Rendered notices.
 */
const Notices: React.FC = () => {
	const context = useContext( StripeBillingMigrationNoticeContext );

	return (
		<>
			<MigrationCompletedNotice
				completedMigrationCount={ context.migratedCount }
			/>
			<MigrateOptionNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
				startMigration={ () => {
					context.startMigration();
				} }
				isLoading={ context.isResolvingMigrateRequest }
				hasResolved={ context.hasResolvedMigrateRequest }
			/>
			<MigrateAutomaticallyNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
			/>
			<MigrationInProgressNotice
				stripeBillingSubscriptionCount={ context.subscriptionCount }
			/>
		</>
	);
};

export default Notices;
