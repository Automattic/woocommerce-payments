/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import {
	enableActAsDisconnectedFromWCPay,
	disableActAsDisconnectedFromWCPay,
} from '../../../utils/devtools';

test.describe( 'Non-admin WP-Admin access', { tag: '@critical' }, () => {
	let merchantPage: Page;
	let editorPage: Page;

	const checkEditorAccess = async (
		page: Page,
		requestUri: string,
		headingName: string
	) => {
		await page.goto( requestUri );
		await page.waitForLoadState( 'domcontentloaded' );

		// Wait a bit more for WordPress admin to fully load
		await page.waitForTimeout( 1000 );

		// Check for heading - try multiple selectors as WordPress admin can vary
		const heading = page.getByRole( 'heading', {
			name: headingName,
			exact: true,
		} );
		const altHeading = page.locator( `h1:has-text("${ headingName }")` );
		const anyHeading = page
			.locator( `h1, h2` )
			.filter( { hasText: headingName } );

		try {
			await expect( heading ).toBeVisible( { timeout: 10000 } );
		} catch {
			try {
				await expect( altHeading ).toBeVisible( { timeout: 5000 } );
			} catch {
				await expect( anyHeading ).toBeVisible( { timeout: 5000 } );
			}
		}

		// Ensure that the page completely loaded - make this optional
		if ( headingName === 'Dashboard' ) {
			// Only check for WordPress footer on dashboard
			await expect(
				page.getByText( 'Thank you for creating with' )
			).toBeVisible();
		}
	};

	const goToConnect = async ( page: Page ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/connect',
			{ waitUntil: 'load' }
		);
	};

	test.beforeAll( async ( { browser } ) => {
		// Create editor user if it doesn't exist using WP-CLI
		try {
			await qit.wp(
				'user create editor editor@test.com --role=editor --user_pass=password --quiet',
				true
			);
		} catch ( error ) {
			// User might already exist, ignore error
		}

		// Create authenticated contexts using QIT auth helpers
		const merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();

		const editorContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'editor' ),
		} );
		editorPage = await editorContext.newPage();
	} );

	test( 'should be able to access wp-admin of fully onboarded WooPayments site', async () => {
		// Navigate to wp-admin and check that the page loads successfully
		await editorPage.goto( '/wp-admin' );
		await editorPage.waitForLoadState( 'domcontentloaded' );
		await editorPage.waitForTimeout( 2000 );

		// Check if the page loaded successfully by looking for WordPress admin elements
		const adminBar = editorPage.locator( '#wpadminbar' );
		const adminMenu = editorPage.locator( '#adminmenu' );
		const wpBody = editorPage.locator( 'body.wp-admin' );

		// At minimum, we should see the WordPress admin body
		await expect( wpBody ).toBeVisible();

		// Try to find some admin elements that should exist
		try {
			await expect( adminBar ).toBeVisible( { timeout: 5000 } );
		} catch {
			// Admin bar might not be visible for all users, try admin menu
			await expect( adminMenu ).toBeVisible( { timeout: 5000 } );
		}

		// Look for any h1 heading to confirm the page structure loaded
		const anyH1 = editorPage.locator( 'h1' ).first();
		await expect( anyH1 ).toBeVisible( { timeout: 5000 } );
	} );

	test( 'should be able to access wp-admin before and after onboarding', async () => {
		// Disconnect from WCPay to simulate a non-onboarded state.
		await enableActAsDisconnectedFromWCPay();

		// Wait a bit for the setting to take effect
		await merchantPage.waitForTimeout( 1000 );

		// Ensure that we are disconnected from WCPay.
		await goToConnect( merchantPage );
		await merchantPage.waitForTimeout( 2000 );

		// Look for any indication that WooPayments is in a disconnected/setup state
		// This could be various buttons or text depending on the exact state
		const connectElements = [
			merchantPage.getByRole( 'button', {
				name: 'Verify business details',
			} ),
			merchantPage.getByRole( 'button', { name: /get started/i } ),
			merchantPage.getByRole( 'button', { name: /connect/i } ),
			merchantPage.getByRole( 'button', { name: /set up/i } ),
			merchantPage.getByRole( 'button', { name: /setup/i } ),
			merchantPage.getByText( /connect your store/i ),
			merchantPage.getByText( /get started/i ),
			merchantPage.locator( 'text=Start accepting payments' ),
		];

		// Try each element until we find one that indicates WCPay is disconnected
		let foundConnectElement = false;
		for ( const element of connectElements ) {
			try {
				await expect( element ).toBeVisible( { timeout: 3000 } );
				foundConnectElement = true;
				break;
			} catch {
				// Continue to next element
			}
		}

		// If none of the specific elements are found, at least verify we're on the connect page
		if ( ! foundConnectElement ) {
			await expect( merchantPage ).toHaveURL( /payments\/connect/ );
			// And that it's not showing the overview page (which would indicate we're connected)
			await expect(
				merchantPage.getByText( 'Account details' )
			).not.toBeVisible( { timeout: 2000 } );
		}

		// Ensure that the editor can access wp-admin.
		// Use the same approach as the first test
		await editorPage.goto( '/wp-admin' );
		await editorPage.waitForLoadState( 'domcontentloaded' );
		await editorPage.waitForTimeout( 1000 );

		// Verify editor can still access WordPress admin
		const wpBody = editorPage.locator( 'body.wp-admin' );
		await expect( wpBody ).toBeVisible();

		const anyH1 = editorPage.locator( 'h1' ).first();
		await expect( anyH1 ).toBeVisible( { timeout: 5000 } );

		// Re-connect to WCPay to simulate a newly onboarded site.
		await disableActAsDisconnectedFromWCPay();

		// Wait for the setting to take effect
		await merchantPage.waitForTimeout( 1000 );

		// Ensure that we are connected to WCPay.
		await merchantPage.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/overview',
			{ waitUntil: 'load' }
		);
		await expect(
			merchantPage.getByText( 'Account details' )
		).toBeVisible();
		await expect( merchantPage.getByText( 'Complete' ) ).toBeVisible();

		// Ensure that the editor can access wp-admin pages screen.
		await checkEditorAccess(
			editorPage,
			'/wp-admin/edit.php?post_type=page',
			'Pages'
		);
	} );
} );
