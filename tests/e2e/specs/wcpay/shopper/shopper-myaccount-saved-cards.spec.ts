/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config, Product } from '../../../config/default';
import { goToMyAccount } from '../../../utils/shopper-navigation';
import { ensureCustomerIsLoggedIn, getShopper } from '../../../utils/helpers';
import {
	addSavedCard,
	confirmCardAuthentication,
	deleteSavedCard,
	placeOrder,
	selectSavedCardOnCheckout,
	setDefaultPaymentMethod,
	setupProductCheckout,
} from '../../../utils/shopper';

type TestVariablesType = {
	[ key: string ]: {
		card: typeof config.cards.basic;
		address: {
			country: string;
			postalCode: string;
		};
		products: [ Product, number ][];
	};
};

const cards = {
	basic: {
		card: config.cards.basic,
		address: {
			country: 'US',
			postalCode: '94110',
		},
		products: [ [ config.products.simple, 1 ] ],
	},
	'3ds': {
		card: config.cards[ '3ds' ],
		address: {
			country: 'US',
			postalCode: '94110',
		},
		products: [ [ config.products.belt, 1 ] ],
	},
	'3ds2': {
		card: config.cards[ '3ds2' ],
		address: {
			country: 'US',
			postalCode: '94110',
		},
		products: [ [ config.products.cap, 1 ] ],
	},
} as TestVariablesType;

test.describe( 'Shopper can save and delete cards', () => {
	let timeAdded: number;
	// Use cards different than other tests to prevent conflicts.
	const card2 = config.cards.basic2;
	let shopperPage: Page = null;

	test.beforeAll( async ( { browser }, { project } ) => {
		shopperPage = ( await getShopper( browser, true, project.use.baseURL ) )
			.shopperPage;

		await ensureCustomerIsLoggedIn( shopperPage, project );
	} );

	async function waitTwentySecondsSinceLastCardAdded( page: Page ) {
		// Make sure that at least 20s had already elapsed since the last card was added.
		// Otherwise, you will get the error message,
		// "You cannot add a new payment method so soon after the previous one."
		// Source: /docker/wordpress/wp-content/plugins/woocommerce/includes/class-wc-form-handler.php#L509-L521

		// Be careful that this is only needed for a successful card addition, so call it only where it's needed the most, to prevent unnecessary delays.
		const timeTestFinished = Date.now();
		const elapsedWaitTime = timeTestFinished - timeAdded;
		const remainingWaitTime =
			20000 > elapsedWaitTime ? 20000 - elapsedWaitTime : 0;

		if ( remainingWaitTime > 0 ) {
			await page.waitForTimeout( remainingWaitTime );
		}
	}

	// No need to run this test for all card types.
	test( 'prevents adding another card for 20 seconds after a card is added', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );

		// Make sure that at least 20s had already elapsed since the last card was added.
		await waitTwentySecondsSinceLastCardAdded( shopperPage );

		await addSavedCard( shopperPage, config.cards.basic, 'US', '94110' );
		// Take note of the time when we added this card
		timeAdded = +Date.now();

		await expect(
			shopperPage.getByText( 'Payment method successfully added.' )
		).toBeVisible();

		// Try to add a new card before 20 seconds have passed
		await addSavedCard( shopperPage, config.cards.basic2, 'US', '94110' );

		// Verify that the card was not added
		try {
			await expect(
				shopperPage.getByText(
					"We're not able to add this payment method. Please refresh the page and try again."
				)
			).toBeVisible( { timeout: 10000 } );
		} catch ( error ) {
			await expect(
				shopperPage.getByText(
					'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
				)
			).toBeVisible();
		}

		// cleanup for the next tests
		await goToMyAccount( shopperPage, 'payment-methods' );
		await deleteSavedCard( shopperPage, config.cards.basic );

		await expect(
			shopperPage.getByText( 'No saved methods found.' )
		).toBeVisible();
	} );

	Object.entries( cards ).forEach(
		( [ cardName, { card, address, products } ] ) => {
			test.describe( 'Testing card: ' + cardName, () => {
				test.beforeAll( async ( {}, { project } ) => {
					await ensureCustomerIsLoggedIn( shopperPage, project );
				} );

				test(
					`should add the ${ cardName } card as a new payment method`,
					{ tag: '@critical' },
					async () => {
						await goToMyAccount( shopperPage, 'payment-methods' );
						// Make sure that at least 20s had already elapsed since the last card was added.
						await waitTwentySecondsSinceLastCardAdded(
							shopperPage
						);

						await addSavedCard(
							shopperPage,
							card,
							address.country,
							address.postalCode
						);
						// Take note of the time when we added this card
						timeAdded = +Date.now();

						if ( cardName === '3ds' || cardName === '3ds2' ) {
							await confirmCardAuthentication( shopperPage );
						}

						await expect(
							shopperPage.getByText(
								'Payment method successfully added.'
							)
						).toBeVisible();

						// Verify that the card was added
						await expect(
							shopperPage.getByText(
								'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
							)
						).not.toBeVisible();
						await expect(
							shopperPage.getByText(
								"We're not able to add this payment method. Please refresh the page and try again."
							)
						).not.toBeVisible();

						await expect(
							shopperPage.getByText(
								`${ card.expires.month }/${ card.expires.year }`
							)
						).toBeVisible();
					}
				);

				test(
					`should be able to purchase with the saved ${ cardName } card`,
					{ tag: '@critical' },
					async () => {
						await setupProductCheckout( shopperPage, products );
						await selectSavedCardOnCheckout( shopperPage, card );
						await placeOrder( shopperPage );
						if ( cardName !== 'basic' ) {
							await confirmCardAuthentication( shopperPage );
						}
						await expect(
							shopperPage.getByRole( 'heading', {
								name: 'Order received',
							} )
						).toBeVisible();
					}
				);

				test(
					`should be able to set the ${ cardName } card as default payment method`,
					{ tag: '@critical' },
					async () => {
						await goToMyAccount( shopperPage, 'payment-methods' );
						// Make sure that at least 20s had already elapsed since the last card was added.
						await waitTwentySecondsSinceLastCardAdded(
							shopperPage
						);

						await addSavedCard( shopperPage, card2, 'US', '94110' );
						// Take note of the time when we added this card
						timeAdded = +Date.now();

						await expect(
							shopperPage.getByText(
								`${ card2.expires.month }/${ card2.expires.year }`
							)
						).toBeVisible();
						await setDefaultPaymentMethod( shopperPage, card2 );
						// Verify that the card was set as default
						await expect(
							shopperPage.getByText(
								'This payment method was successfully set as your default.'
							)
						).toBeVisible();
					}
				);

				test(
					`should be able to delete ${ cardName } card`,
					{ tag: '@critical' },
					async () => {
						await goToMyAccount( shopperPage, 'payment-methods' );
						await deleteSavedCard( shopperPage, card );
						await expect(
							shopperPage.getByText( 'Payment method deleted.' )
						).toBeVisible();

						await deleteSavedCard( shopperPage, card2 );
						await expect(
							shopperPage.getByText( 'Payment method deleted.' )
						).toBeVisible();

						await expect(
							shopperPage.getByText( 'No saved methods found.' )
						).toBeVisible();
					}
				);
			} );
		}
	);
} );
