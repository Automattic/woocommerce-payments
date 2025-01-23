/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { goToMyAccount } from '../../utils/shopper-navigation';
import { getShopper } from '../../utils/helpers';

type CardType = [ string, typeof config.cards.declined ];

const cards: Array< CardType > = [
	[ 'declined', config.cards.declined ],
	[ 'declined-funds', config.cards[ 'declined-funds' ] ],
	[ 'declined-incorrect', config.cards[ 'declined-incorrect' ] ],
	[ 'declined-expired', config.cards[ 'declined-expired' ] ],
	[ 'declined-cvc', config.cards[ 'declined-cvc' ] ],
	[ 'declined-processing', config.cards[ 'declined-processing' ] ],
	[ 'declined-3ds', config.cards[ 'declined-3ds' ] ],
];

test.describe( 'Payment Methods', () => {
	cards.forEach( ( [ cardType, card ] ) => {
		test.describe( `when attempting to add a ${ cardType } card`, () => {
			let shopperPage: Page;
			test.beforeEach( async ( { browser } ) => {
				shopperPage = ( await getShopper( browser ) ).shopperPage;
				await goToMyAccount( shopperPage, 'payment-methods' );
				await shopperPage
					.getByRole( 'link', { name: 'Add payment method' } )
					.click();
				await shopperPage.waitForLoadState( 'networkidle' );
			} );

			test( 'it should not add the card', async () => {
				const { label } = card;

				await shopperPage
					.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
					.first()
					.getByPlaceholder( '1234 1234 1234' )
					.fill( card.number );

				await shopperPage
					.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
					.first()
					.getByPlaceholder( 'MM / YY' )
					.fill( card.expires.month + card.expires.year );

				await shopperPage
					.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
					.first()
					.getByPlaceholder( 'CVC' )
					.fill( card.cvc );

				await shopperPage
					.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
					.first()
					.getByPlaceholder( '12345' )
					.fill( '90210' );

				await shopperPage.click( 'text=Add payment method' );

				await expect(
					shopperPage.getByText( label )
				).not.toBeVisible();
			} );
		} );
	} );
} );
