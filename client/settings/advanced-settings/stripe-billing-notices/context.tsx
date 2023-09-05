/**
 * External dependencies
 */
import { createContext } from 'react';

const StripeBillingMigrationNoticeContext = createContext( {
	isStripeBillingEnabled: false,
	isMigrationOptionShown: false,
	isMigrationInProgressShown: false,
	isMigrationInProgress: false,
	subscriptionCount: 0,
	migratedCount: 0,
	startMigration: () => null,
	isResolving: false,
	hasResolved: false,
} as {
	isStripeBillingEnabled: boolean;
	isMigrationOptionShown: boolean;
	isMigrationInProgressShown: boolean;
	isMigrationInProgress: boolean;
	subscriptionCount: number;
	migratedCount: number;
	startMigration: () => void;
	isResolving: boolean;
	hasResolved: boolean;
} );

export default StripeBillingMigrationNoticeContext;
