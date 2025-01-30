/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	checkPageExists,
	describeif,
	getMerchant,
	getShopper,
} from '../../utils/helpers';
import * as navigation from '../../utils/shopper-navigation';
import * as shopper from '../../utils/shopper';
import { addWCBCheckoutPage } from '../../utils/merchant';
import { shouldRunWCBlocksTests } from '../../utils/constants';
import { config } from '../../config/default';

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

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Checkout failures',
	() => {
		let shopperPage: Page;

		test.beforeAll( async ( { browser }, { project } ) => {
			shopperPage = ( await getShopper( browser ) ).shopperPage;

			if (
				! ( await checkPageExists(
					shopperPage,
					project.use.baseURL + '/checkout-wcb'
				) )
			) {
				const { merchantPage } = await getMerchant( browser );
				await addWCBCheckoutPage( merchantPage );
			}

			await shopper.addCartProduct( shopperPage );
			await navigation.goToCheckoutWCB( shopperPage );
			await shopper.fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
		} );

		test.afterAll( async () => {
			await shopper.emptyCart( shopperPage );
		} );

		for ( const { card, error, auth } of failures ) {
			test( `Should show error – ${ error }`, async () => {
				await shopper.fillCardDetailsWCB( shopperPage, card );
				await shopperPage
					.getByRole( 'button', { name: 'Place Order' } )
					.click();
				if ( auth ) {
					await shopper.confirmCardAuthentication( shopperPage );
				}
				await expect(
					shopperPage.getByLabel( 'Checkout' ).getByText( error )
				).toBeVisible();
			} );
		}
	}
);
