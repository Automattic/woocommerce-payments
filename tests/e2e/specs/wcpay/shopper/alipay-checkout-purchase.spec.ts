/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import * as shopper from '../../../utils/shopper';
import { describeif, getMerchant, getShopper } from '../../../utils/helpers';
import * as merchant from '../../../utils/merchant';
import { config } from '../../../config/default';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';
import { shouldRunWCBlocksTests } from '../../../utils/constants';

test.describe( 'Alipay Checkout', () => {
	let merchantPage: Page;
	let shopperPage: Page;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		await merchant.enablePaymentMethods( merchantPage, [ 'alipay' ] );
	} );

	test.afterAll( async () => {
		await shopper.emptyCart( shopperPage );
		await merchant.disablePaymentMethods( merchantPage, [ 'alipay' ] );
	} );

	test(
		'checkout on shortcode checkout page',
		{ tag: '@critical' },
		async () => {
			await shopper.setupProductCheckout(
				shopperPage,
				[ [ config.products.belt, 1 ] ],
				config.addresses.customer.billing
			);

			await shopperPage
				.locator( '.wc_payment_methods' )
				.getByText( 'alipay' )
				.click();

			await shopper.placeOrder( shopperPage );

			await expect(
				shopperPage.getByText( /Alipay test payment page/ )
			).toBeVisible();

			await shopperPage.getByText( 'Authorize Test Payment' ).click();

			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
			await expect(
				shopperPage.getByRole( 'img', {
					name: 'Alipay',
				} )
			).toBeVisible();
		}
	);

	describeif( shouldRunWCBlocksTests )(
		'checkout on block-based checkout page',
		{ tag: '@critical' },
		() => {
			test( 'completes payment successfully', async () => {
				await shopper.setupProductCheckout(
					shopperPage,
					[ [ config.products.cap, 1 ] ],
					config.addresses.customer.billing
				);
				await goToCheckoutWCB( shopperPage );
				await shopper.fillBillingAddressWCB(
					shopperPage,
					config.addresses.customer.billing
				);

				await shopperPage
					.getByRole( 'radio', {
						name: 'Alipay',
					} )
					.click();

				await shopper.placeOrderWCB( shopperPage, false );

				await expect(
					shopperPage.getByText( /Alipay test payment page/ )
				).toBeVisible();

				await shopperPage.getByText( 'Authorize Test Payment' ).click();

				await expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();
				await expect(
					shopperPage.getByRole( 'img', {
						name: 'Alipay',
					} )
				).toBeVisible();
			} );
		}
	);
} );
