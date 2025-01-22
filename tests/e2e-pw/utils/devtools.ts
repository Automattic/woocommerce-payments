/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

const goToDevToolsSettings = ( page: Page ) =>
	page.goto( 'wp-admin/admin.php?page=wcpaydev', {
		waitUntil: 'load',
	} );

const saveDevToolsSettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	expect( page.getByText( /Settings saved/ ) ).toBeVisible();
};

const getIsCardTestingProtectionEnabled = ( page: Page ) =>
	page.getByLabel( 'Card testing mitigations enabled' ).isChecked();

const toggleCardTestingProtection = ( page: Page ) =>
	page
		.locator( 'label[for="wcpaydev_force_card_testing_protection_on"]' )
		.click();

const getIsActAsDisconnectedFromWCPayEnabled = ( page: Page ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.isChecked();

const toggleActAsDisconnectedFromWCPay = ( page: Page ) =>
	page
		.getByLabel( 'act as disconnected from the Transact Platform Server' )
		.click();

export const enableCardTestingProtection = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( ! ( await getIsCardTestingProtectionEnabled( page ) ) ) {
		await toggleCardTestingProtection( page );
		await saveDevToolsSettings( page );
	}
};

export const disableCardTestingProtection = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( await getIsCardTestingProtectionEnabled( page ) ) {
		await toggleCardTestingProtection( page );
		await saveDevToolsSettings( page );
	}
};

export const enableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( ! ( await getIsActAsDisconnectedFromWCPayEnabled( page ) ) ) {
		await toggleActAsDisconnectedFromWCPay( page );
		await saveDevToolsSettings( page );
	}
};

export const disableActAsDisconnectedFromWCPay = async ( page: Page ) => {
	await goToDevToolsSettings( page );

	if ( await getIsActAsDisconnectedFromWCPayEnabled( page ) ) {
		await toggleActAsDisconnectedFromWCPay( page );
		await saveDevToolsSettings( page );
	}
};
