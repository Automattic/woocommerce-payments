/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import { goToMyAccount } from '../../utils/shopper-navigation';
import { getShopper, useShopper } from '../../utils/helpers';
import {
	addSavedCard,
	clearSavedCardsNonDefault,
	deleteSavedCard,
	setDefaultPaymentMethod,
} from '../../utils/shopper';

test.describe( 'Shopper can save and delete cards', () => {
	let timeAdded: number;
	// Use cards different than other tests to prevent conflicts.
	const card = config.cards.basic2;
	const card2 = config.cards.basic3;

	// File uses shopper for all tests.
	useShopper();

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

	test.beforeAll( async ( { browser } ) => {
		const page = ( await getShopper( browser ) ).shopperPage;
		await goToMyAccount( page, 'payment-methods' );
		// Set the basic card as the default payment method, so it doesn't interfere with the tests.
		const alreadySavedDefault = page.getByRole( 'row', {
			name: config.cards.basic.label,
		} );
		if ( ( await alreadySavedDefault.count() ) > 0 ) {
			// If the basic card is already saved, set it as the default payment method.
			await setDefaultPaymentMethod( page, config.cards.basic );
			await clearSavedCardsNonDefault( page );
		} else {
			// If the basic card is not saved, add it and set it as the default payment method.
			await addSavedCard( page, config.cards.basic, 'US', '94110' );
			timeAdded = +Date.now();
			await setDefaultPaymentMethod( page, config.cards.basic );
			await clearSavedCardsNonDefault( page );
			await waitTwentySecondsSinceLastCardAdded( page );
		}
	} );

	test( 'should add the card as a new payment method', async ( { page } ) => {
		await goToMyAccount( page, 'payment-methods' );
		await addSavedCard( page, card, 'US', '94110' );
		// Take note of the time when we added this card
		timeAdded = +Date.now();

		// Verify that the card was added
		await expect(
			page.getByText(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			)
		).not.toBeVisible();

		await expect(
			page.getByText( 'Payment method successfully added' )
		).toBeVisible();

		await expect(
			page.getByText( `${ card.expires.month }/${ card.expires.year }` )
		).toBeVisible();

		await waitTwentySecondsSinceLastCardAdded( page );
	} );

	test( 'shouldn`t add the card as a new payment method in 20 seconds', async ( {
		page,
	} ) => {
		await goToMyAccount( page, 'payment-methods' );
		// Take note of the time when we added this card
		await addSavedCard( page, card, 'US', '94110' );
		timeAdded = +Date.now();

		// Try to add a new card before 20 seconds have passed
		await addSavedCard( page, card, 'US', '94110' );

		// Verify that the card was not added
		await expect(
			page.getByText(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			)
		).toBeVisible();

		await expect(
			page.getByText( 'Payment method successfully added' )
		).not.toBeVisible();

		await expect(
			page.getByText( `${ card.expires.month }/${ card.expires.year }` )
		).not.toBeVisible();

		await waitTwentySecondsSinceLastCardAdded( page );
	} );

	test( 'should be able to set payment method as default', async ( {
		page,
	} ) => {
		await goToMyAccount( page, 'payment-methods' );
		await addSavedCard( page, card2, 'US', '94110' );
		await expect(
			page.getByText( 'Payment method successfully added' )
		).toBeVisible();
		await expect(
			page.getByText( `${ card2.expires.month }/${ card2.expires.year }` )
		).toBeVisible();
		await setDefaultPaymentMethod( page, card2 );
		// Verify that the card was set as default
		await expect(
			page.getByText(
				'This payment method was successfully set as your default.'
			)
		).toBeVisible();
	} );

	test( 'should be able to delete cards', async ( { page } ) => {
		await goToMyAccount( page, 'payment-methods' );
		await deleteSavedCard( page, card );
		await expect(
			page.getByText( 'Payment method deleted.' )
		).toBeVisible();

		await deleteSavedCard( page, card2 );
		await expect(
			page.getByText( 'Payment method deleted.' )
		).toBeVisible();
	} );
} );
