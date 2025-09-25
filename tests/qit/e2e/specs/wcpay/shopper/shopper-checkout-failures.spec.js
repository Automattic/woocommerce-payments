/* Migrated from ../specs/wcpay/shopper/shopper-checkout-failures.spec.ts for QIT */
/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	cards,
	addToCartFromShopPage,
	setupCheckout,
	fillCardDetails,
	placeOrder,
	confirmCardAuthentication,
	defaultBillingAddress,
} from '../../../helpers/checkout.js';

// Helper to wait for error banner
const waitForBanner = async ( page, errorText ) => {
	await expect( page.getByText( errorText ) ).toBeVisible();
};

test.describe(
	'QIT Shopper > Checkout > Failures with various cards @critical @shopper',
	() => {
		let customerPage;

		test.beforeAll( async ( { browser } ) => {
			customerPage = await browser.newPage();
		} );

		test.afterAll( async () => {
			await customerPage?.close();
		} );

		test.beforeEach( async () => {
			await addToCartFromShopPage( customerPage );
			await setupCheckout( customerPage, defaultBillingAddress );
		} );

		test( 'should throw an error that the card was simply declined', async () => {
			await fillCardDetails( customerPage, cards.declined );
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				'Error: Your card was declined.'
			);
		} );

		test( 'should throw an error that the card expiration date is in the past', async () => {
			await fillCardDetails( customerPage, cards[ 'declined-expired' ] );
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				'Error: Your card has expired.'
			);
		} );

		test( 'should throw an error that the card CVV number is invalid', async () => {
			await fillCardDetails(
				customerPage,
				cards[ 'invalid-cvv-number' ]
			);
			await customerPage.keyboard.press( 'Tab' );
			const frameHandle = await customerPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);
			const stripeFrame = await frameHandle.contentFrame();
			const cvcErrorText = await stripeFrame.locator(
				'p#Field-cvcError'
			);
			await expect( cvcErrorText ).toHaveText(
				'Your card’s security code is incomplete.'
			);
		} );

		test( 'should throw an error that the card was declined due to insufficient funds', async () => {
			await fillCardDetails( customerPage, cards[ 'declined-funds' ] );
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				'Error: Your card has insufficient funds.'
			);
		} );

		test( 'should throw an error that the card was declined due to expired card', async () => {
			await fillCardDetails( customerPage, cards[ 'declined-expired' ] );
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				'Error: Your card has expired.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect CVC number', async () => {
			await fillCardDetails( customerPage, cards[ 'declined-cvc' ] );
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				"Error: Your card's security code is incorrect."
			);
		} );

		test( 'should throw an error that the card was declined due to processing error', async () => {
			await fillCardDetails(
				customerPage,
				cards[ 'declined-processing' ]
			);
			await placeOrder( customerPage );
			await waitForBanner(
				customerPage,
				'Error: An error occurred while processing your card. Try again in a little bit.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect card number', async () => {
			await fillCardDetails(
				customerPage,
				cards[ 'declined-incorrect' ]
			);
			const frameHandle = await customerPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);
			const stripeFrame = await frameHandle.contentFrame();
			const numberErrorText = await stripeFrame.locator(
				'p#Field-numberError'
			);
			await expect( numberErrorText ).toHaveText(
				'Your card number is invalid.'
			);
		} );

		test( 'should throw an error that the card was declined due to invalid 3DS card', async () => {
			await fillCardDetails( customerPage, cards[ 'declined-3ds' ] );
			await placeOrder( customerPage );
			await confirmCardAuthentication( customerPage, false );
			await waitForBanner(
				customerPage,
				'We are unable to authenticate your payment method. Please choose a different payment method and try again.'
			);
		} );
	}
);
