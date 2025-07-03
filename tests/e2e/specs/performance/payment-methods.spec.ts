/**
 * External dependencies
 */
import test, { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	recreatePerformanceFile,
	logPerformanceResult,
	measureCheckoutMetrics,
	averageMetrics,
} from '../../utils/performance';
import { getMerchant, getShopper } from '../../utils/helpers';
import { emptyCart, setupProductCheckout } from '../../utils/shopper';
import { activateWooPay, deactivateWooPay } from '../../utils/merchant';

test.describe( 'Checkout page performance', () => {
	let shopperPage: Page;
	let merchantPage: Page;

	test.beforeAll( async ( { browser }, { project } ) => {
		shopperPage = ( await getShopper( browser, true, project.use.baseURL ) )
			.shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;

		// Start a new file for every run.
		recreatePerformanceFile();
	} );

	test.describe( 'Stripe', () => {
		test.beforeEach( async () => {
			await setupProductCheckout( shopperPage );
		} );

		test.afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await emptyCart( shopperPage );
		} );

		test( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				shopperPage,
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);
			logPerformanceResult(
				'Stripe: Average',
				averageMetrics( results )
			);
		} );
	} );

	test.describe( 'WooPay', () => {
		test.beforeEach( async () => {
			// Activate WooPay
			await activateWooPay( merchantPage );

			// Setup cart
			await setupProductCheckout( shopperPage );
		} );

		test.afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await emptyCart( shopperPage );

			// Deactivate WooPay
			await deactivateWooPay( merchantPage );
		} );

		test( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				shopperPage,
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);
			logPerformanceResult(
				'WooPay: Average',
				averageMetrics( results )
			);
		} );
	} );
} );
