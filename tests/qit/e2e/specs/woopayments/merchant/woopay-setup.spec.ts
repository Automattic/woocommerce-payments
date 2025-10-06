/**
 * Internal dependencies
 */
import { test } from '../../../fixtures/auth';
import { activateWooPay, deactivateWooPay } from '../../../utils/merchant';

test.describe( 'WooPay setup', { tag: '@merchant' }, () => {
	let wasWooPayEnabled: boolean;

	test.beforeAll( async ( { adminPage } ) => {
		// Check initial WooPay state and activate if needed
		wasWooPayEnabled = await activateWooPay( adminPage );
	} );

	test.afterAll( async ( { adminPage } ) => {
		// Restore original WooPay state
		if ( ! wasWooPayEnabled ) {
			await deactivateWooPay( adminPage );
		}
	} );

	test( 'can disable the WooPay feature', async ( { adminPage } ) => {
		await deactivateWooPay( adminPage );
	} );

	test( 'can enable the WooPay feature', async ( { adminPage } ) => {
		await activateWooPay( adminPage );
	} );
} );
