/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToMyAccount } from '../../../utils/shopper-navigation';
import {
	ensureCustomerIsLoggedIn,
	getShopper,
	isUIUnblocked,
} from '../../../utils/helpers';
import {
	addSavedCard,
	confirmCardAuthentication,
} from '../../../utils/shopper';

type CardType = [ string, typeof config.cards.declined, string ];

const cards: Array< CardType > = [
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

test.describe( 'Payment Methods', () => {
	let shopperPage: Page;

	test.beforeAll( async ( { browser }, { project } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		await ensureCustomerIsLoggedIn( shopperPage, project );
	} );

	test.beforeEach( async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
	} );

	cards.forEach( ( [ cardType, card, errorText ] ) => {
		test.describe( `when attempting to add a ${ cardType } card`, () => {
			test( 'it should not add the card', async () => {
				const { label } = card;

				await addSavedCard( shopperPage, card, 'US' );

				if ( 'declined-3ds' === cardType ) {
					await confirmCardAuthentication( shopperPage, false );
					await isUIUnblocked( shopperPage );
				}

				// Verify that we get the expected error message.
				await expect( shopperPage.getByRole( 'alert' ) ).toHaveText(
					errorText
				);

				// Declined incorrect card also puts the errorText under the card number field.
				if ( 'declined-incorrect' === cardType ) {
					await expect(
						shopperPage
							.frameLocator(
								'iframe[name^="__privateStripeFrame"]'
							)
							.first()
							.getByRole( 'alert' )
					).toContainText( errorText );
				}

				// Verify that the card is not added to the list of payment methods.
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

			await shopperPage.waitForLoadState( 'networkidle' );

			//This will simulate selecting another payment gateway
			await shopperPage.$eval(
				'input[name="payment_method"]:checked',
				( input ) => {
					( input as HTMLInputElement ).checked = false;
				}
			);

			await shopperPage
				.getByRole( 'button', { name: 'Add payment method' } )
				.click();

			await expect( shopperPage.getByRole( 'alert' ) ).not.toBeVisible();
		}
	);
} );
