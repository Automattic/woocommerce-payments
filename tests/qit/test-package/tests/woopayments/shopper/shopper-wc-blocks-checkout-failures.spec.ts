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
		error: 'Your card’s expiration year is in the past.',
	},
	{
		card: config.cards[ 'invalid-cvv-number' ],
		error: 'Your security code is incomplete.',
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
		error: 'Your card has been declined.',
		auth: true,
	},
];

const errorsInsideStripeFrame = [
	config.cards[ 'invalid-exp-date' ],
	config.cards[ 'invalid-cvv-number' ],
	config.cards[ 'declined-incorrect' ],
];

const paymentElementFrameSelector =
	'#payment-method .wcpay-payment-element iframe[name^="__privateStripeFrame"]';

const assertCheckoutError = async (
	page: Page,
	card: typeof config.cards.basic,
	errorMessage: string
) => {
	if ( errorsInsideStripeFrame.includes( card ) ) {
		await expect(
			page
				.frameLocator( paymentElementFrameSelector )
				.getByText( errorMessage )
		).toBeVisible();
		return;
	}

	await expect(
		page.locator( '.wc-block-checkout__form' ).getByText( errorMessage )
	).toBeVisible();
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

				await assertCheckoutError( shopperPage, card, error );
			} );
		}

		test( 'should successfully complete order after retrying with a valid card without refreshing the page', async () => {
			// First attempt: declined card.
			await fillCardDetailsWCB( shopperPage, config.cards.declined );
			await placeOrderWCB( shopperPage, false );

			await assertCheckoutError(
				shopperPage,
				config.cards.declined,
				'Your card was declined.'
			);

			// Second attempt: valid card, same page, no refresh.
			await fillCardDetailsWCB( shopperPage, config.cards.basic );
			await placeOrderWCB( shopperPage, true );
		} );
	}
);
