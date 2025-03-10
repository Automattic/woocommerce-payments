/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getEditor, getMerchant } from '../../../utils/helpers';
import {
	enableActAsDisconnectedFromWCPay,
	disableActAsDisconnectedFromWCPay,
} from '../../../utils/devtools';
import { goToConnect } from '../../../utils/merchant-navigation';

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

		await expect(
			page.getByRole( 'heading', { name: headingName, exact: true } )
		).toBeVisible();

		// Ensure that the page completely loaded.
		await expect(
			page.getByText( 'Thank you for creating with' )
		).toBeVisible();
	};

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		editorPage = ( await getEditor( browser ) ).editorPage;
	} );

	test( 'should be able to access wp-admin of fully onboarded WooPayments site', async () => {
		await checkEditorAccess( editorPage, '/wp-admin', 'Dashboard' );
	} );

	test( 'should be able to access wp-admin before and after onboarding', async () => {
		// Disconnect from WCPay to simulate a non-onboarded state.
		await enableActAsDisconnectedFromWCPay( merchantPage );

		// Ensure that we are disconnected from WCPay.
		await goToConnect( merchantPage );
		await expect(
			merchantPage.getByRole( 'button', {
				name: 'Verify business details',
			} )
		).toBeVisible();

		// Ensure that the editor can access wp-admin.
		await checkEditorAccess( editorPage, '/wp-admin', 'Dashboard' );

		// Re-connect to WCPay to simulate a newly onboarded site.
		await disableActAsDisconnectedFromWCPay( merchantPage );

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
