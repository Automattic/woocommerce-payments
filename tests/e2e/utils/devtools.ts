/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

// The dev-tools settings page (served by the woocommerce-payments-dev-tools
// plugin, pulled from its own trunk during E2E setup) is intermittently truncated
// mid-render by a PHP fatal, which drops its "Save Changes" submit button. A bare
// click on the missing button would wait out the full 120s test timeout, so we
// verify the page rendered fully, reload-and-retry when it didn't, and fail fast
// with a clear message otherwise.
const devToolsRenderTimeoutMs = 15 * 1000;
const devToolsMaxLoadAttempts = 3;

const goToDevToolsSettings = async ( page: Page ) => {
	for ( let attempt = 1; attempt <= devToolsMaxLoadAttempts; attempt++ ) {
		await page.goto( '/wp-admin/admin.php?page=wcpaydev', {
			waitUntil: 'load',
		} );

		// The submit button renders after every settings section, so its presence
		// is proof the page was not truncated before it.
		const renderedFully = await page
			.getByRole( 'button', { name: 'Save Changes' } )
			.waitFor( { state: 'visible', timeout: devToolsRenderTimeoutMs } )
			.then( () => true )
			.catch( () => false );

		if ( renderedFully ) {
			return;
		}
	}

	throw new Error(
		`WCPay Dev Tools settings page did not render its "Save Changes" button after ${ devToolsMaxLoadAttempts } attempts; ` +
			'it was likely truncated by a PHP fatal during render. See the "PHP fatals" group in the E2E run log.'
	);
};

const saveDevToolsSettings = async ( page: Page ) => {
	await page
		.getByRole( 'button', { name: 'Save Changes' } )
		.click( { timeout: devToolsRenderTimeoutMs } );
	// Wait for the save request to complete and verify success.
	await page.waitForLoadState( 'load' );
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
