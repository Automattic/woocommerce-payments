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

	await shopper.selectPaymentMethod( page, 'Alipay' );

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

				const alipayRadio = shopperPage.getByRole( 'radio', {
					name: 'Alipay',
				} );
				await alipayRadio.click();
				await expect( alipayRadio ).toBeChecked();

				// TEMP diagnostics for the WP nightly flake. Do not merge.
				const t0 = Date.now();
				const diag: string[] = [];
				const log = ( s: string ) =>
					diag.push( `+${ Date.now() - t0 }ms ${ s }` );
				const relevant = ( u: string ) =>
					/store\/v1\/checkout|stripe|alipay|wc-ajax|admin-ajax|order-received|wcpay/i.test(
						u
					);
				shopperPage.on( 'request', ( r ) => {
					if ( relevant( r.url() ) ) {
						log( `REQ ${ r.method() } ${ r.url() }` );
					}
				} );
				shopperPage.on( 'response', async ( r ) => {
					if ( ! relevant( r.url() ) ) return;
					let body = '';
					if ( /store\/v1\/checkout/.test( r.url() ) ) {
						body = ( await r.text().catch( () => '' ) ).slice(
							0,
							1500
						);
					}
					log( `RES ${ r.status() } ${ r.url() } ${ body }` );
				} );
				shopperPage.on( 'requestfailed', ( r ) =>
					log( `FAILED ${ r.url() } ${ r.failure()?.errorText }` )
				);
				shopperPage.on( 'framenavigated', ( f ) => {
					if ( f === shopperPage.mainFrame() ) {
						log( `NAV ${ f.url() }` );
					}
				} );
				shopperPage.on( 'console', ( m ) =>
					log( `CONSOLE ${ m.type() } ${ m.text().slice( 0, 500 ) }` )
				);
				shopperPage.on( 'pageerror', ( e ) =>
					log( `PAGEERROR ${ e.message }` )
				);
				await shopperPage
					.context()
					.tracing.start( { screenshots: true, snapshots: true } );

				try {
					await shopper.placeOrderWCB( shopperPage, false );
					log( 'clicked Place Order' );

					await expect(
						shopperPage.getByText( /Alipay test payment page/ )
					).toBeVisible();
				} catch ( e ) {
					log( `expect failed, url=${ shopperPage.url() }` );
					await shopperPage
						.waitForURL( /stripe|alipay|order-received/, {
							timeout: 45000,
						} )
						.then( () =>
							log( `late NAV ok ${ shopperPage.url() }` )
						)
						.catch( () =>
							log(
								`still stuck after 45s, url=${ shopperPage.url() }`
							)
						);
					throw e;
				} finally {
					await shopperPage.context().tracing.stop( {
						path: test.info().outputPath( 'shopper-trace.zip' ),
					} );
					console.log( `ALIPAY-DIAG\n${ diag.join( '\n' ) }` );
					await test.info().attach( 'alipay-diag', {
						body: diag.join( '\n' ),
						contentType: 'text/plain',
					} );
				}

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
