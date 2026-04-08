/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToMyAccount } from '../../../utils/shopper-navigation';
import { isUIUnblocked } from '../../../utils/helpers';
import {
	addSavedCard,
	confirmCardAuthentication,
} from '../../../utils/shopper';

const cards: Array< [ string, typeof config.cards.declined, string ] > = [
	[ 'declined', config.cards.declined, 'Error: Your card was declined.' ],
	[
		'declined-funds',
		config.cards[ 'declined-funds' ],
		'Error: Your card has insufficient funds.',
	],
	[
		'declined-incorrect',
		config.cards[ 'declined-incorrect' ],
		'Your card number is invalid.',
	],
	[
		'declined-expired',
		config.cards[ 'declined-expired' ],
		'Error: Your card has expired.',
	],
	[
		'declined-cvc',
		config.cards[ 'declined-cvc' ],
		"Error: Your card's security code is incorrect.",
	],
	[
		'declined-processing',
		config.cards[ 'declined-processing' ],
		'Error: An error occurred while processing your card. Try again in a little bit.',
	],
	[
		'declined-3ds',
		config.cards[ 'declined-3ds' ],
		'We are unable to authenticate your payment method. Please choose a different payment method and try again.',
	],
];

test.describe( 'Payment Methods', { tag: '@shopper' }, () => {
	let shopperContext: BrowserContext;
	let shopperPage: Page;

	test.beforeAll( async ( { browser } ) => {
		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();
	} );

	test.afterAll( async () => {
		await shopperContext?.close();
	} );

	test.beforeEach( async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
	} );

	cards.forEach( ( [ cardType, card, errorText ] ) => {
		test.describe( `when attempting to add a ${ cardType } card`, () => {
			test( 'it should not add the card', async () => {
				const { label } = card;

				await addSavedCard( shopperPage, card, 'US' );

				if ( cardType === 'declined-3ds' ) {
					await confirmCardAuthentication( shopperPage, false );
					await isUIUnblocked( shopperPage );
				}

				// For declined-incorrect, Stripe validates client-side and shows
				// error only in the iframe - form is never submitted to WooCommerce
				if ( cardType === 'declined-incorrect' ) {
					await expect(
						shopperPage
							.frameLocator(
								'iframe[name^="__privateStripeFrame"]'
							)
							.first()
							.getByRole( 'alert' )
					).toContainText( errorText );
				} else {
					// For all other decline types, the error comes from server
					// and displays as a WooCommerce notice on the page
					await expect(
						shopperPage.getByRole( 'alert' )
					).toHaveText( errorText, { timeout: 30000 } );
				}

				await expect(
					shopperPage.getByText( label )
				).not.toBeVisible();
			} );
		} );
	} );

	test(
		'it should not show error when adding payment method on another gateway',
		{ tag: '@critical' },
		async () => {
			await shopperPage
				.getByRole( 'link', { name: 'Add payment method' } )
				.click();

			await shopperPage.waitForLoadState( 'domcontentloaded' );
			await isUIUnblocked( shopperPage );
			await expect(
				shopperPage.locator( 'input[name="payment_method"]' ).first()
			).toBeVisible( { timeout: 5000 } );

			await shopperPage.$eval(
				'input[name="payment_method"]:checked',
				( input ) => {
					( input as HTMLInputElement ).checked = false;
				}
			);

			await shopperPage
				.getByRole( 'button', { name: 'Add payment method' } )
				.click();
			await shopperPage.waitForTimeout( 300 );

			await expect( shopperPage.getByRole( 'alert' ) ).not.toBeVisible();
		}
	);
} );
