/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import {
	useStripeBilling,
	useStripeBillingMigration,
	useSettings,
	useManualCapture,
} from 'wcpay/data/settings';
import StripeBillingSection from '../stripe-billing-section';

jest.mock( 'wcpay/data/settings', () => ( {
	useStripeBilling: jest.fn(),
	useStripeBillingMigration: jest.fn(),
	useSettings: jest.fn(),
	useManualCapture: jest.fn(),
} ) );

const mockedUseStripeBilling = useStripeBilling as jest.Mock;
const mockedUseStripeBillingMigration = useStripeBillingMigration as jest.Mock;
const mockedUseSettings = useSettings as jest.Mock;
const mockedUseManualCapture = useManualCapture as jest.Mock;

// Query notices via their DOM class so the a11y-speak announcement region
// (which mirrors the text) doesn't produce duplicate matches.
const findNoticeByText = ( text: RegExp ): HTMLElement | null => {
	const notices = Array.from(
		document.querySelectorAll( '.woopayments-stripe-billing-notice' )
	) as HTMLElement[];
	return (
		notices.find( ( notice ) => text.test( notice.textContent ?? '' ) ) ??
		null
	);
};

const automaticNoticeMatch = /will be automatically migrated/i;
const inProgressNoticeMatch = /being migrated from Stripe off-site billing/i;
const optionNoticeMatch = /We suggest migrating/i;

interface MigrationOptions {
	isMigrationInProgress?: boolean;
	migratedCount?: number;
	subscriptionCount?: number;
	startMigration?: jest.Mock;
	isResolving?: boolean;
	hasResolved?: boolean;
}

const setUpMocks = ( {
	isStripeBillingEnabled,
	isSaving = false,
	updateIsStripeBillingEnabled = jest.fn(),
	migration = {},
}: {
	isStripeBillingEnabled: boolean;
	isSaving?: boolean;
	updateIsStripeBillingEnabled?: jest.Mock;
	migration?: MigrationOptions;
} ) => {
	mockedUseStripeBilling.mockReturnValue( [
		isStripeBillingEnabled,
		updateIsStripeBillingEnabled,
	] );
	mockedUseManualCapture.mockReturnValue( [ false, jest.fn() ] );
	mockedUseSettings.mockReturnValue( {
		isLoading: false,
		isSaving,
		isDirty: false,
		saveSettings: jest.fn(),
	} );
	mockedUseStripeBillingMigration.mockReturnValue( [
		migration.isMigrationInProgress ?? false,
		migration.migratedCount ?? 0,
		migration.subscriptionCount ?? 0,
		migration.startMigration ?? jest.fn(),
		migration.isResolving ?? false,
		migration.hasResolved ?? false,
	] );
};

describe( 'StripeBillingSection', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'on initial load with Stripe Billing disabled and no subscriptions, shows no notices', () => {
		setUpMocks( { isStripeBillingEnabled: false } );

		render( <StripeBillingSection /> );

		expect( findNoticeByText( automaticNoticeMatch ) ).toBeNull();
		expect( findNoticeByText( inProgressNoticeMatch ) ).toBeNull();
		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();
	} );

	it( 'on initial load with Stripe Billing enabled, does not show the migrate-option or migrate-automatically notices yet', () => {
		setUpMocks( {
			isStripeBillingEnabled: true,
			migration: { subscriptionCount: 3 },
		} );

		render( <StripeBillingSection /> );

		// The migrate-option notice is locked off because Stripe Billing was
		// enabled on mount. The migrate-automatically notice is eligible but
		// its content is hidden while Stripe Billing itself is still enabled.
		expect( findNoticeByText( automaticNoticeMatch ) ).toBeNull();
		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();
	} );

	it( 'on initial load with Stripe Billing disabled and existing subscriptions, shows the migrate-option notice', () => {
		setUpMocks( {
			isStripeBillingEnabled: false,
			migration: { subscriptionCount: 4 },
		} );

		render( <StripeBillingSection /> );

		expect( findNoticeByText( optionNoticeMatch ) ).not.toBeNull();
		expect( findNoticeByText( inProgressNoticeMatch ) ).toBeNull();
	} );

	it( 'toggling Stripe Billing without saving does not flip the snapshot notices', async () => {
		const updateIsStripeBillingEnabled = jest.fn();
		setUpMocks( {
			isStripeBillingEnabled: false,
			updateIsStripeBillingEnabled,
			migration: { subscriptionCount: 2 },
		} );

		render( <StripeBillingSection /> );

		const toggle = screen.getByTestId( 'stripe-billing-toggle' );
		await userEvent.click( toggle );

		expect( updateIsStripeBillingEnabled ).toHaveBeenCalledWith( true );

		// Because the mock does not propagate the update, we still see the
		// disabled-on-mount snapshot: migrate-option notice remains, no
		// migrate-automatically notice.
		expect( findNoticeByText( optionNoticeMatch ) ).not.toBeNull();
		expect( findNoticeByText( automaticNoticeMatch ) ).toBeNull();
	} );

	it( 'after saving with Stripe Billing disabled from an enabled mount, shows the migration-progress notice', () => {
		setUpMocks( {
			isStripeBillingEnabled: true,
			isSaving: true,
			migration: { subscriptionCount: 5 },
		} );

		const { rerender } = render( <StripeBillingSection /> );

		// While saving there is no progress notice yet.
		expect( findNoticeByText( inProgressNoticeMatch ) ).toBeNull();

		// Simulate save completion with Stripe Billing now disabled.
		setUpMocks( {
			isStripeBillingEnabled: false,
			isSaving: false,
			migration: { subscriptionCount: 5 },
		} );

		rerender( <StripeBillingSection /> );

		expect( findNoticeByText( inProgressNoticeMatch ) ).not.toBeNull();
		expect( findNoticeByText( automaticNoticeMatch ) ).toBeNull();
	} );

	it( 'after saving with Stripe Billing enabled from a disabled mount, locks the migrate-option notice off', () => {
		setUpMocks( {
			isStripeBillingEnabled: false,
			isSaving: true,
			migration: { subscriptionCount: 3 },
		} );

		const { rerender } = render( <StripeBillingSection /> );

		// Complete the save with Stripe Billing now enabled.
		setUpMocks( {
			isStripeBillingEnabled: true,
			isSaving: false,
			migration: { subscriptionCount: 3 },
		} );

		rerender( <StripeBillingSection /> );

		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();

		// Now the user disables again and saves. The migrate-option notice
		// stays locked off; migration-progress appears.
		setUpMocks( {
			isStripeBillingEnabled: true,
			isSaving: true,
			migration: { subscriptionCount: 3 },
		} );
		rerender( <StripeBillingSection /> );

		setUpMocks( {
			isStripeBillingEnabled: false,
			isSaving: false,
			migration: { subscriptionCount: 3 },
		} );
		rerender( <StripeBillingSection /> );

		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();
		expect( findNoticeByText( inProgressNoticeMatch ) ).not.toBeNull();
	} );

	it( 'when the migrate-option request resolves, shows the migration-progress notice', () => {
		setUpMocks( {
			isStripeBillingEnabled: false,
			migration: { subscriptionCount: 2, hasResolved: false },
		} );

		const { rerender } = render( <StripeBillingSection /> );

		expect( findNoticeByText( optionNoticeMatch ) ).not.toBeNull();

		// Simulate the "Begin migration" request finishing.
		setUpMocks( {
			isStripeBillingEnabled: false,
			migration: { subscriptionCount: 2, hasResolved: true },
		} );

		rerender( <StripeBillingSection /> );

		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();
		expect( findNoticeByText( inProgressNoticeMatch ) ).not.toBeNull();
	} );

	it( 'when the server already reports a migration in progress, shows the migration-progress notice on mount', () => {
		setUpMocks( {
			isStripeBillingEnabled: false,
			migration: {
				subscriptionCount: 4,
				isMigrationInProgress: true,
			},
		} );

		render( <StripeBillingSection /> );

		expect( findNoticeByText( inProgressNoticeMatch ) ).not.toBeNull();
		expect( findNoticeByText( optionNoticeMatch ) ).toBeNull();
	} );

	it( 'dismissing the migration-progress notice hides it', async () => {
		setUpMocks( {
			isStripeBillingEnabled: false,
			migration: {
				subscriptionCount: 2,
				isMigrationInProgress: true,
			},
		} );

		render( <StripeBillingSection /> );

		const notice = findNoticeByText( inProgressNoticeMatch );
		if ( notice === null ) {
			throw new Error( 'migration-progress notice was not rendered' );
		}

		const dismissButton = within( notice ).getByRole( 'button', {
			name: /close|dismiss/i,
		} );

		await userEvent.click( dismissButton );

		expect( findNoticeByText( inProgressNoticeMatch ) ).toBeNull();
	} );
} );
