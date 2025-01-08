/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */

import { config } from '../../config/default';
import * as shopper from '../../utils/shopper';

test.describe( 'Failures with various cards', () => {
	const notice = 'div.woocommerce-NoticeGroup-checkout';

	const waitForBanner = async ( page: Page, errorText: string ) => {
		const banner = page.locator( notice );
		await banner.waitFor( { state: 'visible' } );
		await expect( banner ).toContainText( errorText );
	};

	test.beforeEach( async ( { page } ) => {
		await shopper.addCartProduct( page );

		await page.goto( '/checkout/' );
		await shopper.fillBillingAddress(
			page,
			config.addresses.customer.billing
		);
	} );

	test( 'should throw an error that the card was simply declined', async ( {
		page,
	} ) => {
		await shopper.fillCardDetails( page, config.cards.declined );
		await shopper.placeOrder( page );

		await waitForBanner( page, 'Error: Your card was declined.' );
	} );
} );
