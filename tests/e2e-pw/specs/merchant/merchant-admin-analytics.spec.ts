/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../utils/shopper';
import { config } from '../../config/default';
import { getMerchant, getShopper, useMerchant } from '../../utils/helpers';
import {
	activateMulticurrency,
	ensureOrderIsProcessed,
	isMulticurrencyEnabled,
	setOption,
	tableDataHasLoaded,
} from '../../utils/merchant';
import { goToOrderAnalytics } from '../../utils/merchant-navigation';

test.describe( 'Admin order analytics', () => {
	let orderId: string;

	// Use the merchant user for this test suite.
	useMerchant();

	test.beforeAll( async ( { browser } ) => {
		const { shopperPage } = await getShopper( browser );
		const { merchantPage } = await getMerchant( browser );

		if ( false === ( await isMulticurrencyEnabled( merchantPage ) ) ) {
			await activateMulticurrency( merchantPage );
		}

		// Place an order to ensure the analytics data is correct.
		await shopperPage.goto( '/cart/' );
		await shopper.addCartProduct( shopperPage );

		await shopper.setupCheckout(
			shopperPage,
			config.addresses.customer.billing
		);
		await shopper.fillCardDetails( shopperPage, config.cards.basic );
		await shopper.placeOrder( shopperPage );

		// Get the order ID
		const orderIdField = shopperPage.locator(
			'.woocommerce-order-overview__order.order > strong'
		);

		orderId = await orderIdField.innerText();
		await ensureOrderIsProcessed( merchantPage, orderId );

		// Ensure that the tour modal is not shown.
		await setOption(
			merchantPage,
			'woocommerce_orders_report_date_tour_shown',
			'yes'
		);
	} );

	test( 'should load without any errors', async ( { browser } ) => {
		const { merchantPage } = await getMerchant( browser );
		await goToOrderAnalytics( merchantPage );
		await tableDataHasLoaded( merchantPage );

		const ordersTitle = merchantPage.getByRole( 'heading', {
			name: 'Orders',
			level: 1,
			exact: true,
		} );
		await expect( ordersTitle ).toBeVisible();
		await expect( merchantPage ).toHaveScreenshot();
	} );
	test( 'orders table should have the customer currency column', async ( {
		browser,
	} ) => {
		const { merchantPage } = await getMerchant( browser );
		await goToOrderAnalytics( merchantPage );
		await tableDataHasLoaded( merchantPage );

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
