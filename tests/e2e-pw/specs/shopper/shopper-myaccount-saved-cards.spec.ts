/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { goToMyAccount } from '../../utils/shopper-navigation';
import { getAnonymousShopper } from '../../utils/helpers';
import {
	addSavedCard,
	deleteSavedCard,
	placeOrderWithOptions,
	setDefaultPaymentMethod,
} from '../../utils/shopper';
import RestAPI from '../../utils/rest-api';

test.describe( 'Shopper can save and delete cards', () => {
	let timeAdded: number;
	// Use cards different than other tests to prevent conflicts.
	const card = config.cards.basic2;
	const card2 = config.cards.basic3;
	let shopperPage: Page = null;
	const customerBillingConfig =
		config.addresses[ 'subscriptions-customer' ].billing;

	test.beforeAll( async ( { browser }, { project } ) => {
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

	test( 'should add the card as a new payment method', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
		await addSavedCard( shopperPage, card, 'US', '94110' );
		// Take note of the time when we added this card
		timeAdded = +Date.now();

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

	test( 'shouldn`t add the card as a new payment method in 20 seconds', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
		// Take note of the time when we added this card
		await addSavedCard( shopperPage, card, 'US', '94110' );
		timeAdded = +Date.now();

		// Try to add a new card before 20 seconds have passed
		await addSavedCard( shopperPage, card, 'US', '94110' );

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
				`${ card.expires.month }/${ card.expires.year }`
			)
		).not.toBeVisible();

		await waitTwentySecondsSinceLastCardAdded( shopperPage );
	} );

	test( 'should be able to set payment method as default', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
		await addSavedCard( shopperPage, card2, 'US', '94110' );
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

	test( 'should be able to delete cards', async () => {
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
} );
