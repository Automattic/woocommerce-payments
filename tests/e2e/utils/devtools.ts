/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

const goToDevToolsSettings = ( page: Page ) =>
	page.goto( '/wp-admin/admin.php?page=wcpaydev', {
		waitUntil: 'load',
	} );

const saveDevToolsSettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	await page.waitForLoadState( 'networkidle' );
	await expect( page.getByText( /Settings saved/ ) ).toBeVisible();
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
