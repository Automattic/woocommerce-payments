/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { getAnonymousShopper, getShopper } from '../../utils/helpers';
import {
	confirmCardAuthentication,
	deleteSavedCard,
	fillCardDetails,
	placeOrder,
	selectSavedCardOnCheckout,
	setSavePaymentMethod,
	setupProductCheckout,
} from '../../utils/shopper';
import { goToMyAccount, goToShop } from '../../utils/shopper-navigation';

type CardType = [ string, typeof config.cards.basic ];

const cards: Array< CardType > = [
	[ 'basic', config.cards.basic ],
	[ '3ds', config.cards[ '3ds' ] ],
];

test.describe( 'Saved cards', () => {
	cards.forEach( ( [ cardType, card ] ) => {
		test.describe(
			`When using a ${ cardType } card added on checkout`,
			() => {
				let shopperPage: Page;
				test.beforeAll( async ( { browser }, { project } ) => {
					shopperPage = (
						await getShopper( browser, true, project.use.baseURL )
					 ).shopperPage;
				} );
				test( 'should save the card', async ( {} ) => {
					await goToShop( shopperPage );
					await setupProductCheckout( shopperPage );
					await fillCardDetails( shopperPage, card );
					await setSavePaymentMethod( shopperPage, true );
					await placeOrder( shopperPage );
					if ( cardType === '3ds' ) {
						await confirmCardAuthentication( shopperPage );
					}
					await expect(
						shopperPage.getByText( 'Order received' ).first()
					).toBeVisible();

					await goToMyAccount( shopperPage, 'payment-methods' );
					await expect(
						shopperPage.getByText( card.label )
					).toBeVisible();
					await expect(
						shopperPage.getByText(
							`${ card.expires.month }/${ card.expires.year }`
						)
					).toBeVisible();
				} );

				test( 'should process a payment with the saved card', async ( {} ) => {
					await goToShop( shopperPage );
					await setupProductCheckout( shopperPage );
					await selectSavedCardOnCheckout( shopperPage, card );
					await placeOrder( shopperPage );
					if ( cardType === '3ds' ) {
						await confirmCardAuthentication( shopperPage );
					}
					await shopperPage.waitForLoadState( 'networkidle' );
					await expect(
						shopperPage.getByText( 'Order received' ).first()
					).toBeVisible();
				} );

				test( 'should delete the card', async ( {} ) => {
					await goToMyAccount( shopperPage, 'payment-methods' );
					await deleteSavedCard( shopperPage, card );
					await expect(
						shopperPage.getByText( 'Payment method deleted' )
					).toBeVisible();
				} );

				test( 'should not allow guest user to save the card', async ( {
					browser,
				} ) => {
					const page: Page = ( await getAnonymousShopper( browser ) )
						.shopperPage;
					await goToShop( page );
					await setupProductCheckout( page );
					await expect(
						page.getByLabel(
							'Save payment information to my account for future purchases.'
						)
					).not.toBeVisible();
				} );
			}
		);
	} );
} );
