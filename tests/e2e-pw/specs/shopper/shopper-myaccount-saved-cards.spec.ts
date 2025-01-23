/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { goToMyAccount, goToShop } from '../../utils/shopper-navigation';
import { getAnonymousShopper } from '../../utils/helpers';
import {
	addSavedCard,
	confirmCardAuthentication,
	deleteSavedCard,
	placeOrder,
	placeOrderWithOptions,
	selectSavedCardOnCheckout,
	setDefaultPaymentMethod,
	setupProductCheckout,
} from '../../utils/shopper';
import RestAPI from '../../utils/rest-api';

const cards = {
	basic: config.cards.basic,
	'3ds': config.cards[ '3ds' ],
	'3ds2': config.cards[ '3ds2' ],
} as { [ key: string ]: typeof config.cards.basic };

test.describe( 'Shopper can save and delete cards', () => {
	let timeAdded: number;
	// Use cards different than other tests to prevent conflicts.
	const card2 = config.cards.basic3;
	let shopperPage: Page = null;
	const customerBillingConfig =
		config.addresses[ 'subscriptions-customer' ].billing;

	test.beforeAll( async ( { browser }, { project } ) => {
		// Delete the user, if present, and create a new one with a new order.
		// This is required to avoid running this test on a customer that already has a saved card.
		const restApi = new RestAPI( project.use.baseURL );
		await restApi.deleteCustomerByEmailAddress(
			customerBillingConfig.email
		);

		shopperPage = ( await getAnonymousShopper( browser ) ).shopperPage;
		await placeOrderWithOptions( shopperPage, {
			billingAddress: customerBillingConfig,
			createAccount: true,
		} );
	} );

	async function waitTwentySecondsSinceLastCardAdded( page: Page ) {
		// Make sure that at least 20s had already elapsed since the last card was added.
		// Otherwise, you will get the error message,
		// "You cannot add a new payment method so soon after the previous one."
		// Source: /docker/wordpress/wp-content/plugins/woocommerce/includes/class-wc-form-handler.php#L509-L521
		const timeTestFinished = Date.now();
		const elapsedWaitTime = timeTestFinished - timeAdded;
		const remainingWaitTime =
			20000 > elapsedWaitTime ? 20000 - elapsedWaitTime : 0;

		await page.waitForTimeout( remainingWaitTime );
	}

	// No need to run this test for all card types.
	test( 'prevents adding another card for 20 seconds after a card is added', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
		// Take note of the time when we added this card
		await addSavedCard( shopperPage, config.cards.basic, 'US', '94110' );
		timeAdded = +Date.now();

		// Try to add a new card before 20 seconds have passed
		await addSavedCard( shopperPage, config.cards.basic2, 'US', '94110' );

		// Verify that the card was not added
		await expect(
			shopperPage.getByText(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			)
		).toBeVisible();

		await expect(
			shopperPage.getByText( 'Payment method successfully added' )
		).not.toBeVisible();

		await expect(
			shopperPage.getByText(
				`${ config.cards.basic2.expires.month }/${ config.cards.basic2.expires.year }`
			)
		).not.toBeVisible();

		await waitTwentySecondsSinceLastCardAdded( shopperPage );
		// cleanup for the next tests
		await goToMyAccount( shopperPage, 'payment-methods' );
		await deleteSavedCard( shopperPage, config.cards.basic );
	} );

	Object.entries( cards ).forEach( ( [ cardName, card ] ) => {
		test.describe( 'Testing card: ' + cardName, () => {
			test( `should add the ${ cardName } card as a new payment method`, async () => {
				await goToMyAccount( shopperPage, 'payment-methods' );
				await addSavedCard( shopperPage, card, 'US', '94110' );
				// Take note of the time when we added this card
				timeAdded = +Date.now();

				if ( cardName === '3ds' || cardName === '3ds2' ) {
					await confirmCardAuthentication( shopperPage );
				}

				await shopperPage.waitForURL( /\/my-account\/payment-methods/, {
					waitUntil: 'load',
				} );

				// Verify that the card was added
				await expect(
					shopperPage.getByText(
						'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
					)
				).not.toBeVisible();

				await expect(
					shopperPage.getByText( 'Payment method successfully added' )
				).toBeVisible();

				await expect(
					shopperPage.getByText(
						`${ card.expires.month }/${ card.expires.year }`
					)
				).toBeVisible();

				await waitTwentySecondsSinceLastCardAdded( shopperPage );
			} );

			test( `should be able to purchase with the saved ${ cardName } card`, async () => {
				await goToShop( shopperPage );
				await setupProductCheckout( shopperPage );
				await selectSavedCardOnCheckout( shopperPage, card );
				if ( cardName === 'basic' ) {
					await placeOrder( shopperPage );
				} else {
					await shopperPage
						.getByRole( 'button', { name: 'Place order' } )
						.click();
					await confirmCardAuthentication( shopperPage );
				}

				await shopperPage.waitForURL( /\/order-received\//, {
					waitUntil: 'load',
				} );
				await expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();
			} );

			test( `should be able to set the ${ cardName } card as default payment method`, async () => {
				await goToMyAccount( shopperPage, 'payment-methods' );
				await addSavedCard( shopperPage, card2, 'US', '94110' );
				// Take note of the time when we added this card
				timeAdded = +Date.now();

				await expect(
					shopperPage.getByText( 'Payment method successfully added' )
				).toBeVisible();
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
			} );

			test( `should be able to delete ${ cardName } card`, async () => {
				await goToMyAccount( shopperPage, 'payment-methods' );
				await deleteSavedCard( shopperPage, card );
				await expect(
					shopperPage.getByText( 'Payment method deleted.' )
				).toBeVisible();

				await deleteSavedCard( shopperPage, card2 );
				await expect(
					shopperPage.getByText( 'Payment method deleted.' )
				).toBeVisible();
			} );

			test.afterAll( async () => {
				waitTwentySecondsSinceLastCardAdded( shopperPage );
			} );
		} );
	} );
} );
