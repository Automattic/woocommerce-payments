/**
 * Express Checkout (ECE) wallet buttons - shopper flows.
 *
 * Real ECE flow end to end (Stripe.js, Store API cart, backend confirm, order);
 * only the wallet sheet is faked, since Google/Apple/Amazon Pay are OS UI
 * Playwright can't reach. A test-only mu-plugin injects the fake sheet and mints
 * real Stripe test credentials, so a completed order proves the pipeline.
 *
 * The proxy only injects when Apple/Google Pay is on, so `beforeAll` enables the
 * gateways and `afterAll` restores them.
 */

/**
 * External dependencies
 */
import { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import { describeif, getAnonymousShopper } from '../../../utils/helpers';
import {
	enableExpressCheckout,
	disableExpressCheckout,
	enableAmazonPay,
	disableAmazonPay,
} from '../../../utils/merchant';
import { goToProductPageBySlug } from '../../../utils/shopper-navigation';
import { eceStripeCalls, lastCall, EceCall } from '../../../utils/ece-stripe-proxy';

// No shared constant exists in the QIT package, so mirror the local gate the
// other QIT subscription specs use.
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

// Scope to the real ECE container so the hidden availability probe's identical
// fake buttons (mounted off-screen) don't trip Playwright strict mode.
const walletButton = ( page: Page, wallet: string ) =>
	page
		.locator( '#wcpay-express-checkout-element' )
		.getByTestId( `ece-fake-button-${ wallet }` );

// Real backend confirm + redirect takes longer than the default expect timeout.
const orderReceivedTimeout = 30000;

// Currency string to integer minor units, tolerant of either decimal separator
// (the last '.' or ',' is the decimal point) so it doesn't couple to the locale.
const amountToCents = ( text: string ): number => {
	const match = text.replace( /\s/g, '' ).match( /[\d.,]+/ );
	if ( ! match ) {
		return NaN;
	}
	let value = match[ 0 ];
	const lastDot = value.lastIndexOf( '.' );
	const lastComma = value.lastIndexOf( ',' );
	let decimal = '';
	if ( lastDot > lastComma ) {
		decimal = '.';
	} else if ( lastComma > lastDot ) {
		decimal = ',';
	}
	if ( decimal ) {
		const thousands = decimal === '.' ? ',' : '.';
		value = value.split( thousands ).join( '' ).replace( decimal, '.' );
	} else {
		value = value.replace( /[.,]/g, '' );
	}
	return Math.round( parseFloat( value ) * 100 );
};

// Grand total off the order-received page - the block confirmation table (row
// "Total:") or the classic thank-you template.
const orderReceivedTotalCents = async ( page: Page ): Promise< number > => {
	const blockTotal = page
		.locator( 'tr', {
			has: page.locator(
				'.wc-block-order-confirmation-totals__label',
				{ hasText: /^\s*Total:?\s*$/ }
			),
		} )
		.locator( '.woocommerce-Price-amount' )
		.first();
	const classicTotal = page
		.locator( '.woocommerce-order-overview__total .woocommerce-Price-amount' )
		.first();
	// toHaveURL resolves before the totals paint, so wait for the total to be
	// visible rather than reading a bare count() snapshot.
	await expect( blockTotal.or( classicTotal ) ).toBeVisible();
	const text = ( await blockTotal.count() )
		? await blockTotal.innerText()
		: await classicTotal.innerText();
	return amountToCents( text );
};

let merchantContext: BrowserContext;
let merchantPage: Page;
let priorEceState: boolean;
let priorAmazonPayState: boolean;

test.describe( 'Express Checkout (ECE) wallet buttons', () => {
	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();
		// The enable helpers return whether it was ALREADY on, so we only tear
		// each back down in afterAll if we were the ones who turned it on. Amazon
		// Pay is a separate method, so it needs its own toggle for its button.
		priorEceState = await enableExpressCheckout( merchantPage );
		priorAmazonPayState = await enableAmazonPay( merchantPage );
	} );

	test.afterAll( async () => {
		if ( ! priorAmazonPayState ) {
			await disableAmazonPay( merchantPage );
		}
		if ( ! priorEceState ) {
			await disableExpressCheckout( merchantPage );
		}
		await merchantContext?.close();
	} );

	test(
		'Google Pay completes a purchase from the product page',
		{ tag: '@critical' },
		async ( { browser } ) => {
			const { shopperPage, shopperContext } = await getAnonymousShopper(
				browser
			);
			await goToProductPageBySlug( shopperPage, 'belt' );

			const button = walletButton( shopperPage, 'googlePay' );
			await expect( button ).toBeVisible();
			await button.click();

			// Physical product auto-applies the default US address and first rate,
			// so the happy path is open-sheet → Pay.
			await expect(
				shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
			).toBeVisible();
			await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

			await expect( shopperPage ).toHaveURL( /order-received/, {
				timeout: orderReceivedTimeout,
			} );
			await expect(
				shopperPage.getByText( /order received|thank you/i ).first()
			).toBeVisible();

			await shopperContext.close();
		}
	);

	test( 'Amazon Pay completes a purchase', async ( { browser } ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		await goToProductPageBySlug( shopperPage, 'belt' );

		// Amazon Pay is its own method, not a card wallet, so the fake mints a
		// real amazon_pay credential and confirms through Stripe's redirect
		// authorization, the same as the other redirect methods (Alipay/Klarna).
		const button = walletButton( shopperPage, 'amazonPay' );
		await expect( button ).toBeVisible();
		await button.click();

		await expect(
			shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
		).toBeVisible();
		await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

		await shopperPage.getByText( 'Authorize Test Payment' ).click();

		await expect( shopperPage ).toHaveURL( /order-received/, {
			timeout: orderReceivedTimeout,
		} );

		await shopperContext.close();
	} );

	test( 'virtual product declares no shipping to Stripe', async ( {
		browser,
	} ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		// album is the sample data's virtual product, so it needs no shipping.
		await goToProductPageBySlug( shopperPage, 'album' );

		const button = walletButton( shopperPage, 'googlePay' );
		await expect( button ).toBeVisible();
		await button.click();

		// No shipping needed, so no shipping section - open-sheet → Pay.
		await expect(
			shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
		).toBeVisible();
		await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

		await expect( shopperPage ).toHaveURL( /order-received/, {
			timeout: orderReceivedTimeout,
		} );

		const calls = await eceStripeCalls( shopperPage );
		// Only the clicked button runs the click flow, so exactly one
		// event.click.resolve exists - lastCall is unambiguous here.
		const clickResolve = lastCall( calls, 'event.click.resolve' );

		expect( clickResolve?.args[ 0 ] ).toMatchObject( {
			shippingAddressRequired: false,
		} );
		// Client sets shippingRates to `undefined` (not []) when shipping isn't
		// required, and the JSON recorder drops the undefined key; normalize to [].
		expect( ( clickResolve?.args[ 0 ]?.shippingRates ?? [] ).length ).toBe(
			0
		);

		await shopperContext.close();
	} );

	describeif( shouldRunSubscriptionsTests )( 'Subscriptions', () => {
		test( 'subscription purchase sets up an off-session payment', async ( {
			browser,
		} ) => {
			// Subscriptions need a logged-in customer, and QIT has no
			// recreate-customer helper, so reuse the shared `customer`. A fresh
			// context still isolates the proxy's sessionStorage recorder.
			const shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			const shopperPage = await shopperContext.newPage();
			await goToProductPageBySlug(
				shopperPage,
				'subscription-no-signup-fee-product'
			);

			const button = walletButton( shopperPage, 'googlePay' );
			await expect( button ).toBeVisible();
			await button.click();

			await expect(
				shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
			).toBeVisible();
			await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

			await expect( shopperPage ).toHaveURL( /order-received/, {
				timeout: orderReceivedTimeout,
			} );

			const calls = await eceStripeCalls( shopperPage );
			const named = ( c: EceCall ) => `${ c.target }.${ c.method }`;

			// Both the probe and the real button call stripe.elements() with
			// off_session here, so tell them apart by create() opts: only the real
			// button's carries button styling (buttonHeight).
			const buttonCreate = calls
				.filter(
					( c ) =>
						named( c ) === 'elements.create' &&
						c.args[ 0 ] === 'expressCheckout' &&
						c.args[ 1 ] &&
						'buttonHeight' in c.args[ 1 ]
				)
				.at( -1 );

			// stripe.elements() runs just before its create(), so the button's
			// config is the last stripe.elements() before that create.
			const buttonElements = calls
				.filter(
					( c ) =>
						named( c ) === 'stripe.elements' &&
						buttonCreate !== undefined &&
						c.seq < buttonCreate.seq
				)
				.at( -1 );

			// Proves the subscription-page config (the real button's, via
			// buttonHeight) carries off_session. Guard the discriminator first so a
			// miss fails here, not as an opaque toMatchObject on undefined.
			expect( buttonCreate ).toBeDefined();
			expect( buttonElements ).toBeDefined();
			expect( buttonElements?.args[ 0 ] ).toMatchObject( {
				setupFutureUsage: 'off_session',
			} );

			await shopperContext.close();
		} );
	} );

	test( 'a declined card surfaces an error and does not complete', async ( {
		browser,
	} ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		await goToProductPageBySlug( shopperPage, 'belt' );

		const button = walletButton( shopperPage, 'googlePay' );
		await expect( button ).toBeVisible();
		await button.click();

		await expect(
			shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
		).toBeVisible();
		// The token mints fine; the decline happens at backend charge time, so
		// onConfirmHandler aborts the redirect and surfaces a notice instead.
		await shopperPage
			.getByTestId( 'ece-fake-card' )
			.selectOption( 'tok_chargeDeclined' );
		await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

		const errorNotice = shopperPage
			.locator( '.wc-block-components-notice-banner, .woocommerce-error' )
			.filter( { hasText: /declin|not able to process|problem/i } );

		await expect( errorNotice.first() ).toBeVisible( {
			timeout: orderReceivedTimeout,
		} );
		await expect( shopperPage ).not.toHaveURL( /order-received/ );

		await shopperContext.close();
	} );

	test( 'recalculates the total when a different shipping method is selected', async ( {
		browser,
	} ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		await goToProductPageBySlug( shopperPage, 'belt' );

		const button = walletButton( shopperPage, 'googlePay' );
		await expect( button ).toBeVisible();
		await button.click();

		await expect(
			shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
		).toBeVisible();

		// The US zone has two differently-priced rates (free + flat $11), so a
		// second option exists and the total must change. A visible radio also
		// means the address round-trip settled, so the total below is final.
		const rateOptions = shopperPage
			.getByTestId( 'ece-fake-shipping-rate' )
			.locator( 'input[type="radio"]' );
		await expect( rateOptions.nth( 1 ) ).toBeVisible();

		const total = shopperPage.getByTestId( 'ece-fake-total' );
		const totalBefore = await total.innerText();

		// The first rate is pre-selected; picking the second drives the app's
		// shippingratechange, which recomputes the cart and updates the total.
		await rateOptions.nth( 1 ).check();

		await expect( total ).not.toHaveText( totalBefore, {
			timeout: orderReceivedTimeout,
		} );

		await shopperContext.close();
	} );

	test( 'the sheet total is reflected on the order-received page', async ( {
		browser,
	} ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		await goToProductPageBySlug( shopperPage, 'belt' );

		const button = walletButton( shopperPage, 'googlePay' );
		await expect( button ).toBeVisible();
		await button.click();

		await expect(
			shopperPage.getByTestId( 'ece-fake-wallet-sheet' )
		).toBeVisible();

		// Shipping options present means the address round-trip resolved, so the
		// total is the final amount that will be charged.
		await expect(
			shopperPage
				.getByTestId( 'ece-fake-shipping-rate' )
				.locator( 'input[type="radio"]' )
				.first()
		).toBeVisible();

		// Capture the total before paying; the sheet is torn down on confirm.
		const sheetTotalCents = amountToCents(
			await shopperPage.getByTestId( 'ece-fake-total' ).innerText()
		);
		expect( sheetTotalCents ).toBeGreaterThan( 0 );

		await shopperPage.getByTestId( 'ece-fake-wallet-pay' ).click();

		await expect( shopperPage ).toHaveURL( /order-received/, {
			timeout: orderReceivedTimeout,
		} );

		expect( await orderReceivedTotalCents( shopperPage ) ).toBe(
			sheetTotalCents
		);

		await shopperContext.close();
	} );
} );
