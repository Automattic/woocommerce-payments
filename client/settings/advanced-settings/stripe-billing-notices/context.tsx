/**
 * External dependencies
 */
import { createContext } from 'react';

const StripeBillingMigrationNoticeContext = createContext( {
	isStripeBillingEnabled: false,
	savedIsStripeBillingEnabled: false,
	isMigrationOptionShown: false,
	isMigrationInProgressShown: false,
	isMigrationInProgress: false,
	hasSavedSettings: false,
	subscriptionCount: 0,
	migratedCount: 0,
	startMigration: () => null,
	isResolvingMigrateRequest: false,
	hasResolvedMigrateRequest: false,
} as {
	isStripeBillingEnabled: boolean;
	savedIsStripeBillingEnabled: boolean;
	isMigrationOptionShown: boolean;
	isMigrationInProgressShown: boolean;
	isMigrationInProgress: boolean;
	hasSavedSettings: boolean;
	subscriptionCount: number;
	migratedCount: number;
	startMigration: () => void;
	isResolvingMigrateRequest: boolean;
	hasResolvedMigrateRequest: boolean;
} );

export default StripeBillingMigrationNoticeContext;
