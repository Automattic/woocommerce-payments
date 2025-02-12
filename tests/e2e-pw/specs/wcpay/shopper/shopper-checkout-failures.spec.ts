/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */

import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';

test.describe(
	'Shopper > Checkout > Failures with various cards',
	{ tag: '@critical' },
	() => {
		const waitForBanner = async ( page: Page, errorText: string ) => {
			await expect( page.getByText( errorText ) ).toBeVisible();
		};

		test.beforeEach( async ( { page } ) => {
			await shopper.addToCartFromShopPage( page );
			await shopper.setupCheckout( page );
			await shopper.selectPaymentMethod( page );
		} );

		test( 'should throw an error that the card was simply declined', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails( page, config.cards.declined );
			await shopper.placeOrder( page );

			await waitForBanner( page, 'Error: Your card was declined.' );
		} );

		test( 'should throw an error that the card expiration date is in the past', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( page );

			await waitForBanner( page, 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card CVV number is invalid', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'invalid-cvv-number' ]
			);

			await page.keyboard.press( 'Tab' );

			const frameHandle = await page.waitForSelector(
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

		test( 'should throw an error that the card was declined due to insufficient funds', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-funds' ]
			);
			await shopper.placeOrder( page );

			await waitForBanner(
				page,
				'Error: Your card has insufficient funds.'
			);
		} );

		test( 'should throw an error that the card was declined due to expired card', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( page );

			await waitForBanner( page, 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card was declined due to incorrect CVC number', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-cvc' ]
			);
			await shopper.placeOrder( page );

			await waitForBanner(
				page,
				"Error: Your card's security code is incorrect."
			);
		} );

		test( 'should throw an error that the card was declined due to processing error', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-processing' ]
			);
			await shopper.placeOrder( page );

			await waitForBanner(
				page,
				'Error: An error occurred while processing your card. Try again in a little bit.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect card number', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-incorrect' ]
			);

			const frameHandle = await page.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);

			const stripeFrame = await frameHandle.contentFrame();
			const numberErrorText = await stripeFrame.locator(
				'p#Field-numberError'
			);

			expect( numberErrorText ).toHaveText(
				'Your card number is invalid.'
			);
		} );

		test( 'should throw an error that the card was declined due to invalid 3DS card', async ( {
			page,
		} ) => {
			await shopper.fillCardDetails(
				page,
				config.cards[ 'declined-3ds' ]
			);
			await shopper.placeOrder( page );

			await shopper.confirmCardAuthentication( page, false );

			await waitForBanner(
				page,
				'We are unable to authenticate your payment method. Please choose a different payment method and try again.'
			);
		} );
	}
);
