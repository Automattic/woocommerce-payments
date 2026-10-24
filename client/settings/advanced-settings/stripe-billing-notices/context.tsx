/**
 * External dependencies
 */
import { createContext } from 'react';

const StripeBillingMigrationNoticeContext = createContext( {
	isStripeBillingEnabled: false,
	savedIsStripeBillingEnabled: false,
	isMigrationOptionShown: false,
	isMigrationInProgress: false,
	hasSavedSettings: false,
	subscriptionCount: 0,
	migratedCount: 0,
	startMigration: () => null,
	isResolvingMigrateRequest: false,
	hasResolvedMigrateRequest: false,
	setIsMigrationInProgress: () => null,
} as {
	isStripeBillingEnabled: boolean;
	savedIsStripeBillingEnabled: boolean;
	isMigrationOptionShown: boolean;
	isMigrationInProgress: boolean;
	hasSavedSettings: boolean;
	subscriptionCount: number;
	migratedCount: number;
	startMigration: () => void;
	isResolvingMigrateRequest: boolean;
	hasResolvedMigrateRequest: boolean;
	setIsMigrationInProgress: ( value: boolean ) => void;
} );

export default StripeBillingMigrationNoticeContext;
