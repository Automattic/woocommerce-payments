/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';

test.describe(
	'Shopper > Checkout > Failures with various cards',
	{ tag: [ '@shopper', '@critical' ] },
	() => {
		const waitForBanner = async ( page: Page, errorText: string ) => {
			await expect( page.getByText( errorText ) ).toBeVisible();
		};

		test.beforeEach( async ( { customerPage } ) => {
			await shopper.emptyCart( customerPage );
			await shopper.addToCartFromShopPage( customerPage );
			await shopper.setupCheckout( customerPage );
			await shopper.selectPaymentMethod( customerPage );
		} );

		test( 'should throw an error that the card was simply declined', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards.declined
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				'Error: Your card was declined.'
			);
		} );

		test( 'should throw an error that the card expiration date is in the past', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				'Error: Your card has expired.'
			);
		} );

		test( 'should throw an error that the card CVV number is invalid', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'invalid-cvv-number' ]
			);

			await customerPage.keyboard.press( 'Tab' );

			const frameHandle = await customerPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);

			const stripeFrame = await frameHandle.contentFrame();
			if ( ! stripeFrame ) {
				throw new Error(
					'Unable to load Stripe frame for CVC error expectation.'
				);
			}

			const cvcErrorText = stripeFrame.locator( 'p#Field-cvcError' );

			await expect( cvcErrorText ).toHaveText(
				'Your card’s security code is incomplete.'
			);
		} );

		test( 'should throw an error that the card was declined due to insufficient funds', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-funds' ]
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				'Error: Your card has insufficient funds.'
			);
		} );

		test( 'should throw an error that the card was declined due to expired card', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				'Error: Your card has expired.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect CVC number', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-cvc' ]
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				"Error: Your card's security code is incorrect."
			);
		} );

		test( 'should throw an error that the card was declined due to processing error', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-processing' ]
			);
			await shopper.placeOrder( customerPage );

			await waitForBanner(
				customerPage,
				'Error: An error occurred while processing your card. Try again in a little bit.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect card number', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-incorrect' ]
			);

			const frameHandle = await customerPage.waitForSelector(
				'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
			);

			const stripeFrame = await frameHandle.contentFrame();
			if ( ! stripeFrame ) {
				throw new Error(
					'Unable to load Stripe frame for card number error expectation.'
				);
			}

			const numberErrorText = stripeFrame.locator(
				'p#Field-numberError'
			);

			await expect( numberErrorText ).toHaveText(
				'Your card number is invalid.'
			);
		} );

		test( 'should throw an error that the card was declined due to invalid 3DS card', async ( {
			customerPage,
		} ) => {
			await shopper.fillCardDetails(
				customerPage,
				config.cards[ 'declined-3ds' ]
			);
			await shopper.placeOrder( customerPage );

			await shopper.confirmCardAuthentication( customerPage, false );

			await waitForBanner(
				customerPage,
				'We are unable to authenticate your payment method. Please choose a different payment method and try again.'
			);
		} );
	}
);
