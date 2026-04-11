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

const cards: TestVariablesType = {
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
};

const makeCardTimingHelper = () => {
	let lastCardAddedAt: number | null = null;

	return {
		// Make sure that at least 20s had already elapsed since the last card was added.
		// Otherwise, you will get the error message,
		// "You cannot add a new payment method so soon after the previous one."
		// Source: /docker/wordpress/wp-content/plugins/woocommerce/includes/class-wc-form-handler.php#L509-L521

		// Be careful that this is only needed for a successful card addition, so call it only where it's needed the most, to prevent unnecessary delays.
		async waitIfNeededBeforeAddingCard( page: Page ) {
			if ( ! lastCardAddedAt ) return;

			const elapsed = Date.now() - lastCardAddedAt;
			const waitTime = 20000 - elapsed;

			if ( waitTime > 0 ) {
				await page.waitForTimeout( waitTime );
			}
		},

		markCardAdded() {
			lastCardAddedAt = Date.now();
		},
	};
};

test.describe( 'Shopper can save and delete cards', () => {
	// Use cards different from other tests to prevent conflicts.
	const card2 = config.cards.basic2;
	let shopperPage: Page;

	const cardTimingHelper = makeCardTimingHelper();

	test.beforeAll( async ( { browser }, { project } ) => {
		shopperPage = ( await getShopper( browser, true, project.use.baseURL ) )
			.shopperPage;

		await ensureCustomerIsLoggedIn( shopperPage, project );

		// calling it first here, just in case a card was added in a previous test.
		cardTimingHelper.markCardAdded();
	} );

	// No need to run this test for all card types.
	test( 'prevents adding another card for 20 seconds after a card is added', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );

		// Make sure that at least 20s had already elapsed since the last card was added.
		await cardTimingHelper.waitIfNeededBeforeAddingCard( shopperPage );

		await addSavedCard( shopperPage, config.cards.basic, 'US', '94110' );
		// Take note of the time when we added this card
		cardTimingHelper.markCardAdded();

		// Try to add a new card before 20 seconds have passed
		await addSavedCard( shopperPage, config.cards.basic2, 'US', '94110' );

		// Verify that the second card was not added.
		// The error could be shown on the add form; navigate to the list to assert state.
		await goToMyAccount( shopperPage, 'payment-methods' );
		await expect(
			shopperPage
				.getByRole( 'row', { name: config.cards.basic.label } )
				.first()
		).toBeVisible();
		await expect(
			shopperPage.getByRole( 'row', { name: config.cards.basic2.label } )
		).toHaveCount( 0 );

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
						await cardTimingHelper.waitIfNeededBeforeAddingCard(
							shopperPage
						);

						await addSavedCard(
							shopperPage,
							card,
							address.country,
							address.postalCode
						);

						if ( cardName === '3ds' || cardName === '3ds2' ) {
							await confirmCardAuthentication( shopperPage );
							// After 3DS, wait for redirect back to Payment methods before asserting
							await expect(
								shopperPage.getByRole( 'heading', {
									name: 'Payment methods',
								} )
							).toBeVisible( { timeout: 30000 } );
						}

						// Record time of addition early to respect the 20s rule across tests
						cardTimingHelper.markCardAdded();

						// Verify that the card was added
						await expect(
							shopperPage.getByText(
								'You cannot add a new payment method so soon after the previous one.'
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
						// Ensure the saved methods table is present before interacting
						await expect(
							shopperPage.getByRole( 'heading', {
								name: 'Payment methods',
							} )
						).toBeVisible();
						// Make sure that at least 20s had already elapsed since the last card was added.
						await cardTimingHelper.waitIfNeededBeforeAddingCard(
							shopperPage
						);

						await addSavedCard( shopperPage, card2, 'US', '94110' );
						// Take note of the time when we added this card
						cardTimingHelper.markCardAdded();

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
