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
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const checkoutWithAlipay = async ( page: Page ): Promise< string > => {
	await shopper.setupProductCheckout(
		page,
		[ [ config.products.belt, 1 ] ],
		config.addresses.customer.billing
	);

	await page.locator( '.wc_payment_methods' ).getByText( 'alipay' ).click();

	await shopper.placeOrder( page );

	await expect( page.getByText( /Alipay test payment page/ ) ).toBeVisible();

	await page.getByText( 'Authorize Test Payment' ).click();

	await expect(
		page.getByRole( 'heading', { name: 'Order received' } )
	).toBeVisible();

	const orderId = page.url().match( /\/order-received\/(\d+)\// )?.[ 1 ];
	if ( ! orderId ) {
		throw new Error(
			`Expected an order-received URL with an order ID, got: ${ page.url() }`
		);
	}
	return orderId;
};

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
			await checkoutWithAlipay( shopperPage );

			await expect(
				shopperPage.getByRole( 'img', {
					name: 'Alipay',
				} )
			).toBeVisible();
		}
	);

	test( 'merchant can see and refund an Alipay order', async () => {
		const orderId = await checkoutWithAlipay( shopperPage );

		await verifyOrderAndRefund( merchantPage, orderId );
	} );

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
