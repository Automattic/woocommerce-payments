/**
 * External dependencies
 */
import { test, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { getMerchant } from '../../utils/helpers';
import { activateWooPay, deactivateWooPay } from '../../utils/merchant';

test.describe( 'WooPay setup', () => {
	let merchantPage: Page;
	let wasWooPayEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasWooPayEnabled = await activateWooPay( merchantPage );
	} );

	test.afterAll( async () => {
		if ( ! wasWooPayEnabled ) {
			await deactivateWooPay( merchantPage );
		}
	} );

	test( 'can disable the WooPay feature', async () => {
		await deactivateWooPay( merchantPage );
	} );

	test( 'can enable the WooPay feature', async () => {
		await activateWooPay( merchantPage );
	} );
} );
