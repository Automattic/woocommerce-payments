/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { describeif, getShopper } from '../../../utils/helpers';
import * as navigation from '../../../utils/shopper-navigation';
import * as shopper from '../../../utils/shopper';
import { shouldRunWCBlocksTests } from '../../../utils/constants';
import { config } from '../../../config/default';

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
		error: 'Your card’s security code is incomplete.',
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
	config.cards[ 'invalid-cvv-number' ],
	config.cards[ 'declined-incorrect' ],
];

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Checkout failures',
	{ tag: [ '@critical', '@blocks' ] },
	() => {
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			shopperPage = ( await getShopper( browser ) ).shopperPage;

			await shopper.addToCartFromShopPage( shopperPage );
			await navigation.goToCheckoutWCB( shopperPage );
			await shopper.fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
		} );

		/**
		 * Reload the page after each test to ensure a clean state.
		 * Otherwise we get flaky results.
		 */
		test.afterEach( async () => {
			await shopperPage.reload();
		} );

		test.afterAll( async () => {
			await shopper.emptyCart( shopperPage );
		} );

		for ( const { card, error, auth } of failures ) {
			test( `Should show error – ${ error }`, async () => {
				await shopper.fillCardDetailsWCB( shopperPage, card );
				await shopper.placeOrderWCB( shopperPage, false );

				if ( auth ) {
					await shopper.confirmCardAuthenticationWCB(
						shopperPage,
						true
					);
				}

				if ( errorsInsideStripeFrame.includes( card ) ) {
					await shopperPage.waitForSelector(
						'.__PrivateStripeElement'
					);
					const frameHandle = await shopperPage.waitForSelector(
						'#payment-method .wcpay-payment-element iframe[name^="__privateStripeFrame"]'
					);
					const stripeFrame = await frameHandle.contentFrame();

					await expect(
						stripeFrame.getByText( error )
					).toBeVisible();
				} else {
					await expect(
						shopperPage
							.locator( '.wc-block-checkout__form' )
							.getByText( error )
					).toBeVisible();
				}
			} );
		}
	}
);
