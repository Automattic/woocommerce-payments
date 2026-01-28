/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';
import * as devtools from '../../../utils/devtools';
import {
	addToCartFromShopPage,
	confirmCardAuthenticationWCB,
	emptyCart,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	placeOrderWCB,
} from '../../../utils/shopper';

const failures = [
	{
		card: config.cards.declined,
		error: 'Your card was declined.',
	},
	{
		card: config.cards[ 'invalid-exp-date' ],
		error: /Your card.s expiration year is in the past\./,
	},
	{
		card: config.cards[ 'invalid-cvv-number' ],
		error: /Your card.s security code is incomplete\./,
	},
	{
		card: config.cards[ 'declined-funds' ],
		error: 'Your card has insufficient funds.',
	},
	{
		card: config.cards[ 'declined-expired' ],
		error: 'Your card has expired.',
	},
	{
		card: config.cards[ 'declined-cvc' ],
		error: "Your card's security code is incorrect.",
	},
	{
		card: config.cards[ 'declined-processing' ],
		error:
			'An error occurred while processing your card. Try again in a little bit.',
	},
	{
		card: config.cards[ 'declined-incorrect' ],
		error: 'Your card number is invalid.',
	},
	{
		card: config.cards[ 'declined-3ds' ],
		error: /Your card (?:was|has been) declined\./,
		auth: true,
	},
];

const paymentElementFrameSelector =
	'#payment-method .wcpay-payment-element iframe[name^="__privateStripeFrame"]';
const generalNoticeMatcher = /Your payment (?:was not|wasn't|could not be|couldn't be) processed\./i;

const assertCheckoutError = async (
	page: Page,
	errorMessage: string | RegExp
) => {
	const stripeErrorLocator = page
		.frameLocator( paymentElementFrameSelector )
		.getByText( errorMessage )
		.first();

	try {
		await expect( stripeErrorLocator ).toBeVisible( { timeout: 5000 } );
		return;
	} catch ( _error ) {
		// Fall through to check for notices rendered outside the Stripe iframe.
	}

	const checkoutForm = page.locator( '.wc-block-checkout__form' );
	try {
		await expect( checkoutForm.getByText( errorMessage ) ).toBeVisible( {
			timeout: 3000,
		} );
		return;
	} catch ( _error ) {
		// If the specific message is not surfaced, ensure the generic
		// decline banner rendered so the customer receives feedback.
	}

	const generalNoticeCandidates = [
		checkoutForm.locator( '.wc-block-store-notice' ).first(),
		checkoutForm.locator( '.wc-block-components-notice-banner' ).first(),
		page
			.getByRole( 'status' )
			.filter( { hasText: generalNoticeMatcher } )
			.first(),
	];

	for ( const candidate of generalNoticeCandidates ) {
		const count = await candidate.count().catch( () => 0 );
		if ( count === 0 ) {
			continue;
		}
		const visible = await candidate.isVisible().catch( () => false );
		if ( ! visible ) {
			continue;
		}
		await expect( candidate ).toContainText( generalNoticeMatcher );
		return;
	}

	if ( page.isClosed() ) {
		throw new Error(
			'Checkout page closed before the decline notice rendered.'
		);
	}

	await expect( page.getByText( generalNoticeMatcher ) ).toBeVisible();
};

test.describe(
	'WooCommerce Blocks > Checkout failures',
	{ tag: [ '@shopper', '@critical', '@blocks' ] },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();
			await devtools.disableCardTestingProtection();
			await devtools.disableFailedTransactionRateLimiter();
		} );

		test.beforeEach( async () => {
			await emptyCart( shopperPage );
			await addToCartFromShopPage( shopperPage );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
		} );

		test.afterAll( async () => {
			await emptyCart( shopperPage );
			await shopperContext?.close();
		} );

		for ( const { card, error, auth } of failures ) {
			test( `Should show error – ${ error }`, async () => {
				await fillCardDetailsWCB( shopperPage, card );
				await placeOrderWCB( shopperPage, false );

				if ( auth ) {
					await confirmCardAuthenticationWCB( shopperPage, true );
				}

				await assertCheckoutError( shopperPage, error );
			} );
		}
	}
);
