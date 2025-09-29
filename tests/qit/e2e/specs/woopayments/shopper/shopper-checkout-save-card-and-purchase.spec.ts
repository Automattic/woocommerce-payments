/**
 * External dependencies
 */
import { test, expect, Page, BrowserContext } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import { goToMyAccount } from '../../../utils/shopper-navigation';

type ConfigProduct = typeof config.products[ keyof typeof config.products ];
type CardType = [ string, typeof config.cards.basic, ConfigProduct[] ];

const cards: CardType[] = [
	[
		'basic',
		config.cards.basic,
		[ config.products.belt, config.products.cap ],
	],
	[
		'3ds',
		config.cards[ '3ds' ],
		[ config.products.sunglasses, config.products.hoodie_with_logo ],
	],
];

test.describe( 'Saved cards', { tag: '@critical' }, () => {
	cards.forEach( ( [ cardType, card, products ] ) => {
		test.describe(
			`When using a ${ cardType } card added on checkout`,
			() => {
				let shopperContext: BrowserContext;
				let shopperPage: Page;

				test.beforeAll( async ( { browser } ) => {
					shopperContext = await browser.newContext();
					shopperPage = await shopperContext.newPage();
					const { username, password } = config.users.customer;
					await qit.loginAs( shopperPage, username, password );
				} );

				test.afterAll( async () => {
					await shopperContext?.close();
				} );

				test.beforeEach( async () => {
					await shopper.emptyCart( shopperPage );
				} );

				test( 'should save the card', async () => {
					await shopper.setupProductCheckout( shopperPage, [
						[ products[ 0 ], 1 ],
					] );
					await shopper.selectPaymentMethod( shopperPage );
					await shopper.fillCardDetails( shopperPage, card );
					await shopper.setSavePaymentMethod( shopperPage, true );
					await shopper.placeOrder( shopperPage );
					if ( cardType === '3ds' ) {
						await shopper.confirmCardAuthentication( shopperPage );
					}
					await expect(
						shopperPage.getByRole( 'heading', {
							name: 'Order received',
						} )
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

				test( 'should process a payment with the saved card', async () => {
					await shopper.setupProductCheckout( shopperPage, [
						[ products[ 1 ], 1 ],
					] );
					await shopper.selectPaymentMethod( shopperPage );
					await shopper.selectSavedCardOnCheckout(
						shopperPage,
						card
					);
					await shopper.placeOrder( shopperPage );
					if ( cardType === '3ds' ) {
						await shopper.confirmCardAuthentication( shopperPage );
					}
					await expect(
						shopperPage.getByRole( 'heading', {
							name: 'Order received',
						} )
					).toBeVisible();
				} );

				test( 'should delete the card', async () => {
					await goToMyAccount( shopperPage, 'payment-methods' );
					await shopper.deleteSavedCard( shopperPage, card );
					await expect(
						shopperPage.getByText( 'Payment method deleted' )
					).toBeVisible();
				} );

				test( 'should not allow guest user to save the card', async ( {
					browser,
				} ) => {
					const guestContext = await browser.newContext();
					const guestPage = await guestContext.newPage();

					try {
						await shopper.setupProductCheckout( guestPage );
						await shopper.selectPaymentMethod( guestPage );
						await expect(
							guestPage.getByLabel(
								'Save payment information to my account for future purchases.'
							)
						).not.toBeVisible();
					} finally {
						await shopper.emptyCart( guestPage );
						await guestContext.close();
					}
				} );
			}
		);
	} );
} );
