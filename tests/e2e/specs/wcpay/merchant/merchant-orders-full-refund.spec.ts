/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getMerchant, getShopper } from '../../../utils/helpers';
import {
	deactivateMulticurrency,
	isMulticurrencyEnabled,
} from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import {
	goToOrder,
	goToPaymentDetails,
} from '../../../utils/merchant-navigation';
import { submitFullRefund } from '../../../utils/merchant-orders';

test.describe( 'WooCommerce Payments - Full Refund', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let orderId: string;
	let orderAmount: string;

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		shopperPage = ( await getShopper( browser ) ).shopperPage;

		// Disable multi-currency in the merchant settings. This step is important because local environment setups
		// might have multi-currency enabled. We need to ensure a consistent environment for the test.
		if ( await isMulticurrencyEnabled( merchantPage ) ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test(
		'should process a full refund for an order',
		{ tag: '@critical' },
		async () => {
			// Place an order to refund later and get the order ID so we can open it in the merchant view
			orderId = await shopper.placeOrderWithCurrency(
				shopperPage,
				'USD'
			);

			// Get the order total so we can verify the refund amount
			orderAmount = await shopperPage
				.locator(
					'.woocommerce-order-overview__total .woocommerce-Price-amount'
				)
				.textContent();

			// Open the order
			await goToOrder( merchantPage, orderId );

			await submitFullRefund( merchantPage );
		}
	);

	test( 'should be able to view a refunded transaction', async () => {
		// Get and navigate to payment details
		const paymentIntentId = await merchantPage
			.locator( '#order_data' )
			.getByRole( 'link', {
				name: /pi_/,
			} )
			.innerText();

		await goToPaymentDetails( merchantPage, paymentIntentId );

		// Verify timeline events
		await expect(
			merchantPage.getByText(
				`A payment of ${ orderAmount } was successfully refunded.`
			)
		).toBeVisible();

		await expect(
			merchantPage.getByText( 'Payment status changed to Refunded.' )
		).toBeVisible();

		await expect(
			merchantPage.getByText( 'Reason: No longer wanted' )
		).toBeVisible();

		// TODO: This visual regression test is not flaky, but we should revisit the approach.
		// await expect( merchantPage ).toHaveScreenshot();
	} );
} );
