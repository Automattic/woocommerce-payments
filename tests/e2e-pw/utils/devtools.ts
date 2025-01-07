/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

const goToDevToolsSettings = async ( page: Page ) => {
	await page.goto( 'wp-admin/admin.php?page=wcpaydev', {
		waitUntil: 'load',
	} );
};

const saveDevToolsSettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	expect( page.getByText( /Settings saved/ ) ).toBeVisible();
};

const getIsCardTestingProtectionEnabled = async ( page: Page ) => {
	return (
		( await page
			.locator( '#wcpaydev_force_card_testing_protection_on:checked' )
			.count() ) === 1
	);
};

const toggleCardTestingProtection = async ( page: Page ) => {
	await page
		.locator( 'label[for="wcpaydev_force_card_testing_protection_on"]' )
		.click();
};

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
