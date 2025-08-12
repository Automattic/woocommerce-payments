/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

const goToDevToolsSettings = async ( page: Page ) => {
	await page.goto( '/wp-admin/admin.php?page=wcpaydev', {
		waitUntil: 'domcontentloaded',
	} );

	// Wait for the page to be fully loaded and verify we're on the right page
	await page.waitForLoadState( 'networkidle' );

	// Verify we're on the devtools page by checking for a unique element
	await expect(
		page.getByText( /WooCommerce Payments Dev Tools/ )
	).toBeVisible( { timeout: 10000 } );
};

const saveDevToolsSettings = async ( page: Page ) => {
	// Wait for the page to be fully loaded before trying to interact
	await page.waitForLoadState( 'domcontentloaded' );

	// Wait for the Save Changes button to be available
	const saveButton = page.getByRole( 'button', { name: 'Save Changes' } );
	await expect( saveButton ).toBeVisible( { timeout: 10000 } );
	await expect( saveButton ).toBeEnabled( { timeout: 10000 } );

	await saveButton.click();
	await page.waitForLoadState( 'networkidle' );
	await expect( page.getByText( /Settings saved/ ) ).toBeVisible( {
		timeout: 10000,
	} );
};

const getIsCardTestingProtectionEnabled = ( page: Page ) =>
	page.getByLabel( /Card testing mitigations enabled/ ).isChecked();

const setCardTestingProtection = ( page: Page, enabled: boolean ) =>
	page
		.locator( 'label[for="wcpaydev_force_card_testing_protection_on"]' )
		.setChecked( enabled );

const getIsActAsDisconnectedFromWCPayEnabled = ( page: Page ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.isChecked();

const setActAsDisconnectedFromWCPay = ( page: Page, enabled: boolean ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.setChecked( enabled );

export const enableCardTestingProtection = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( ! ( await getIsCardTestingProtectionEnabled( page ) ) ) {
		await setCardTestingProtection( page, true );
		await saveDevToolsSettings( page );
	}
};

export const disableCardTestingProtection = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( await getIsCardTestingProtectionEnabled( page ) ) {
		await setCardTestingProtection( page, false );
		await saveDevToolsSettings( page );
	}
};

export const enableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( ! ( await getIsActAsDisconnectedFromWCPayEnabled( page ) ) ) {
		await setActAsDisconnectedFromWCPay( page, true );
		await saveDevToolsSettings( page );
	}
};

export const disableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( await getIsActAsDisconnectedFromWCPayEnabled( page ) ) {
		await setActAsDisconnectedFromWCPay( page, false );
		await saveDevToolsSettings( page );
	}
};
