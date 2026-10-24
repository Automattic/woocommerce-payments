/**
 * Express Checkout (ECE) - scaffolding contract guard.
 *
 * The other ECE specs drive the fake sheet; this canary guards the
 * WooPayments-owned pieces around it - the ECE container/wrapper rendering and
 * the reveal state machine (button-ui.js `showContainer`). They're app-owned DOM
 * driven by app-owned logic, so a renamed container or broken reveal path fails
 * a test.
 *
 * The proxy is env-wide, so this isn't a real-Stripe run; the wallet button
 * lives in a cross-origin iframe and can't run headless, so these never assert
 * on it.
 */

/**
 * External dependencies
 */
import { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import { getAnonymousShopper } from '../../../utils/helpers';
import {
	enableExpressCheckout,
	disableExpressCheckout,
} from '../../../utils/merchant';
import { goToProductPageBySlug } from '../../../utils/shopper-navigation';

// WooPayments reveals the container with `is-ready` once the mount → `ready`
// handshake completes. Assert its PRESENCE (the reveal fired); absence would
// race, since the fake fires `ready` almost immediately.
const readyClass = /is-ready/;

// The ready handshake completes shortly after Stripe.js loads; allow headroom.
const revealTimeout = 20000;

test.describe( 'Express Checkout (ECE) scaffolding contract', () => {
	let merchantContext: BrowserContext;
	let merchantPage: Page;
	let priorEceState: boolean;

	// ECE scaffolding only renders when Apple/Google Pay is on, so enable it here
	// and restore the prior state afterwards.
	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();
		priorEceState = await enableExpressCheckout( merchantPage );
	} );

	test.afterAll( async () => {
		if ( ! priorEceState ) {
			await disableExpressCheckout( merchantPage );
		}
		await merchantContext?.close();
	} );

	test( 'the ECE scaffolding mounts and completes its ready handshake', async ( {
		browser,
	} ) => {
		const { shopperPage, shopperContext } = await getAnonymousShopper(
			browser
		);
		await goToProductPageBySlug( shopperPage, 'belt' );

		const container = shopperPage.locator(
			'#wcpay-express-checkout-element'
		);

		// The wallet button inside is a cross-origin iframe that may not be
		// visible headless, so assert the container/wrapper are ATTACHED.
		await expect( container ).toBeAttached();
		await expect(
			shopperPage.locator( '.wcpay-express-checkout-wrapper' )
		).toBeAttached();

		// `is-ready` proves WooPayments' own ECE integration ran end to end.
		await expect( container ).toHaveClass( readyClass, {
			timeout: revealTimeout,
		} );

		await shopperContext.close();
	} );
} );
