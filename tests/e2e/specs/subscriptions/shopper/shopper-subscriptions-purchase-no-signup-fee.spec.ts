/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { shouldRunSubscriptionsTests } from '../../../utils/constants';
import { describeif, getMerchant, getShopper } from '../../../utils/helpers';
import { config } from '../../../config/default';
import {
	goToSubscriptions,
	goToOrder,
} from '../../../utils/merchant-navigation';
import {
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../../utils/shopper';
import { goToProductPageBySlug } from '../../../utils/shopper-navigation';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	isMulticurrencyEnabled,
} from '../../../utils/merchant';

const productName = 'Subscription no signup fee product';
const productSlug = 'subscription-no-signup-fee-product';

const customerBillingConfig = config.addresses.customer.billing;
let orderId;

describeif( shouldRunSubscriptionsTests )(
	'Shopper Subscriptions Purchase No Signup Fee',
	() => {
		test(
			'It should be able to purchase a subscription without a signup fee',
			{ tag: '@critical' },
			async ( { browser }, { project } ) => {
				const { shopperPage } = await getShopper(
					browser,
					true,
					project.use.baseURL
				);
				await goToProductPageBySlug( shopperPage, productSlug );
				await shopperPage
					.getByRole( 'button', { name: 'Add to cart', exact: true } )
					.click();
				await shopperPage.waitForLoadState( 'networkidle' );
				await expect(
					shopperPage.getByText( /has been added to your cart\./ )
				).toBeVisible();
				await setupCheckout( shopperPage, customerBillingConfig );
				const card = config.cards.basic;
				await fillCardDetails( shopperPage, card );
				await placeOrder( shopperPage );
				expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();

				const orderIdField = shopperPage.locator(
					'.woocommerce-order-overview__order.order > strong'
				);
				orderId = await orderIdField.textContent();
			}
		);

		test( 'It should have a charge for subscription cost without fee & an active subscription', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );

			// Disable multi-currency in the merchant settings.
			// This step is important because local environment setups might have multi-currency enabled.
			const wasMultiCurrencyEnabled = await isMulticurrencyEnabled(
				merchantPage
			);
			if ( wasMultiCurrencyEnabled ) {
				await deactivateMulticurrency( merchantPage );
			}

			await goToOrder( merchantPage, orderId );

			// Verify we have an active subscription
			const relatedSubscriptionId = (
				await merchantPage
					.getByRole( 'link', { name: 'Edit order number' } )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );

			const transactionPageLink = await merchantPage
				.getByText( 'Payment via Card', { exact: false } )
				.getByRole( 'link', { name: /pi_.+/ } )
				.getAttribute( 'href' );

			await merchantPage.goto( transactionPageLink, {
				waitUntil: 'load',
			} );

			await expect(
				merchantPage.getByText(
					'A payment of $9.99 was successfully charged.'
				)
			).toBeVisible();

			await goToSubscriptions( merchantPage );

			let subscriptionsRow = merchantPage.locator(
				'#order-' + relatedSubscriptionId
			);

			// Fallback for WC 7.7.0.
			if ( ( await subscriptionsRow.count() ) === 0 ) {
				subscriptionsRow = merchantPage.locator(
					'#post-' + relatedSubscriptionId
				);
			}

			await expect(
				subscriptionsRow.locator( '.subscription-status' )
			).toHaveText( 'Active' );

			await expect(
				subscriptionsRow.locator( '.order_items' )
			).toHaveText( productName );

			await expect(
				subscriptionsRow.locator( '.recurring_total' )
			).toHaveText( /\$9\.99 \/ month/i );

			// Enable multicurrency if it was enabled before.
			if ( wasMultiCurrencyEnabled ) {
				await activateMulticurrency( merchantPage );
			}
		} );
	}
);
