/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../utils/shopper';
import { getMerchant, getShopper } from '../../utils/helpers';
import {
	ensureOrderIsProcessed,
	tableDataHasLoaded,
	waitAndSkipTourComponent,
	activateMulticurrency,
	deactivateMulticurrency,
} from '../../utils/merchant';
import { goToOrderAnalytics } from '../../utils/merchant-navigation';

test.describe( 'Admin order analytics', () => {
	let shopperPage: Page;
	let merchantPage: Page;
	let orderId: string;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;

		await activateMulticurrency( merchantPage );

		// Place an order to ensure the analytics data is correct.
		orderId = await shopper.placeOrderWithCurrency( shopperPage, 'USD' );
		await ensureOrderIsProcessed( merchantPage, orderId );
	} );

	test.afterAll( async () => {
		await deactivateMulticurrency( merchantPage );
	} );

	test( 'should load without any errors', async () => {
		await goToOrderAnalytics( merchantPage );
		await tableDataHasLoaded( merchantPage );
		await waitAndSkipTourComponent(
			merchantPage,
			'.woocommerce-revenue-report-date-tour'
		);

		const ordersTitle = merchantPage.getByRole( 'heading', {
			name: 'Orders',
			level: 1,
			exact: true,
		} );
		await expect( ordersTitle ).toBeVisible();
		await expect( merchantPage ).toHaveScreenshot();
	} );

	test( 'orders table should have the customer currency column', async () => {
		await goToOrderAnalytics( merchantPage );
		await tableDataHasLoaded( merchantPage );
		await waitAndSkipTourComponent(
			merchantPage,
			'.woocommerce-revenue-report-date-tour'
		);

		const columnToggle = merchantPage.getByTitle(
			'Choose which values to display'
		);
		await columnToggle.click();
		const customerCurrencyToggle = merchantPage.getByRole(
			'menuitemcheckbox',
			{
				name: 'Customer Currency',
			}
		);
		await expect( customerCurrencyToggle ).toBeVisible();
		await customerCurrencyToggle.click();
		const customerCurrencyColumn = merchantPage.getByRole( 'columnheader', {
			name: 'Customer Currency',
		} );
		await expect( customerCurrencyColumn ).toBeVisible();
	} );
} );
