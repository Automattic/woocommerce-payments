/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { goToDisputes, tableDataHasLoaded } from '../../../utils/merchant';

test.describe( 'Merchant disputes', { tag: '@merchant' }, () => {
	test( 'Load the disputes list page', async ( { adminPage } ) => {
		await goToDisputes( adminPage );
		await tableDataHasLoaded( adminPage );

		// .nth( 1 ) defines the second instance of the Disputes heading, which is in the table.
		await expect(
			adminPage.getByRole( 'heading', { name: 'Disputes' } ).nth( 1 )
		).toBeVisible();
	} );
} );
