/**
 * External dependencies
 */
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import {
	activateMulticurrency,
	ensureOrderIsProcessed,
	isMulticurrencyEnabled,
	tableDataHasLoaded,
	waitAndSkipTourComponent,
	goToOrderAnalytics,
} from '../../../utils/merchant';
import { placeOrderWithCurrency } from '../../../utils/shopper';

const getWooCommerceVersion = async () => {
	const result = await qit.wp( 'plugin get woocommerce --field=version', true );
	if ( typeof result === 'string' ) {
		return result.trim();
	}
	return result.stdout.trim();
};

test.describe( 'Admin order analytics', { tag: '@merchant' }, () => {
	// Extend timeout for the entire test suite to allow order processing
	test.setTimeout( 120000 );
	let woocommerceVersion = '';

	test.beforeAll( async ( { adminPage, customerPage } ) => {
		// Set explicit timeout for this beforeAll hook
		test.setTimeout( 120000 );
		woocommerceVersion = await getWooCommerceVersion();

		// Ensure multi-currency is enabled for the analytics tests
		if ( false === ( await isMulticurrencyEnabled( adminPage ) ) ) {
			await activateMulticurrency( adminPage );
		}

		// Place an order to ensure the analytics data is correct
		await placeOrderWithCurrency( customerPage, 'USD' );
		await ensureOrderIsProcessed( adminPage );

		// Give analytics more time to process the order data
		await adminPage.waitForTimeout( 2000 );
	} );

	test( 'should load without any errors', async ( { adminPage } ) => {
		await goToOrderAnalytics( adminPage );
		await tableDataHasLoaded( adminPage );
		await waitAndSkipTourComponent(
			adminPage,
			'.woocommerce-revenue-report-date-tour'
		);

		const ordersTitle = adminPage.getByRole( 'heading', {
			name: 'Orders',
			level: 1,
			exact: true,
		} );
		await expect( ordersTitle ).toBeVisible();

		// Check for analytics data with retry mechanism
		let attempts = 0;
		const maxAttempts = 3;

		while ( attempts < maxAttempts ) {
			const noDataText = adminPage.getByText( 'No data to display' );
			const noDataCount = await noDataText.count();

			if ( noDataCount === 0 ) {
				break; // Data is present, exit retry loop
			}

			// If no data on first check, try refreshing
			if ( attempts < maxAttempts - 1 ) {
				await adminPage.reload();
				await tableDataHasLoaded( adminPage );
				await waitAndSkipTourComponent(
					adminPage,
					'.woocommerce-revenue-report-date-tour'
				);
				// Wait a bit more for data to load after refresh
				await adminPage.waitForTimeout( 2000 );
			}

			attempts++;
		}

		// Verify that we have analytics data from the order created in beforeAll
		const finalNoDataText = adminPage.getByText( 'No data to display' );
		await expect( finalNoDataText ).toHaveCount( 0 );

		// TODO: This visual regression test is flaky, we should revisit the approach.
		// await expect( adminPage ).toHaveScreenshot();
	} );

	test( 'orders table should have the customer currency column', async ( {
		adminPage,
	} ) => {
		test.skip(
			woocommerceVersion === '9.9.7',
			'WooCommerce 9.9.7 crashes before rendering the Orders report table.'
		);

		await goToOrderAnalytics( adminPage );
		await tableDataHasLoaded( adminPage );
		await waitAndSkipTourComponent(
			adminPage,
			'.woocommerce-revenue-report-date-tour'
		);

		const columnToggle = adminPage.getByTitle(
			'Choose which values to display'
		);
		await columnToggle.click();
		const customerCurrencyToggle = adminPage.getByRole(
			'menuitemcheckbox',
			{
				name: 'Customer Currency',
			}
		);
		await expect( customerCurrencyToggle ).toBeVisible();
		await customerCurrencyToggle.click();
		const customerCurrencyColumn = adminPage.getByRole( 'columnheader', {
			name: 'Customer Currency',
		} );
		await expect( customerCurrencyColumn ).toBeVisible();
	} );
} );
