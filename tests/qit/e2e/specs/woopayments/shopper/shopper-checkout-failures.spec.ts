/**
 * External dependencies
 */
import { test, expect, BrowserContext, Page } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';

test.describe(
	'Shopper > Checkout > Failures with various cards',
	{ tag: '@critical' },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		const waitForBanner = async ( page: Page, errorText: string ) => {
			await expect( page.getByText( errorText ) ).toBeVisible();
		};

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
			await shopper.addToCartFromShopPage( shopperPage );
			await shopper.setupCheckout( shopperPage );
			await shopper.selectPaymentMethod( shopperPage );
		} );

		test( 'should throw an error that the card was simply declined', async () => {
			await shopper.fillCardDetails( shopperPage, config.cards.declined );
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				shopperPage,
				'Error: Your card was declined.'
			);
		} );

		test( 'should throw an error that the card expiration date is in the past', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner( shopperPage, 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card CVV number is invalid', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'invalid-cvv-number' ]
			);

			await shopperPage.keyboard.press( 'Tab' );

			const frameHandle = await shopperPage.waitForSelector(
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

		test( 'should throw an error that the card was declined due to insufficient funds', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-funds' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				shopperPage,
				'Error: Your card has insufficient funds.'
			);
		} );

		test( 'should throw an error that the card was declined due to expired card', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-expired' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner( shopperPage, 'Error: Your card has expired.' );
		} );

		test( 'should throw an error that the card was declined due to incorrect CVC number', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-cvc' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				shopperPage,
				"Error: Your card's security code is incorrect."
			);
		} );

		test( 'should throw an error that the card was declined due to processing error', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-processing' ]
			);
			await shopper.placeOrder( shopperPage );

			await waitForBanner(
				shopperPage,
				'Error: An error occurred while processing your card. Try again in a little bit.'
			);
		} );

		test( 'should throw an error that the card was declined due to incorrect card number', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-incorrect' ]
			);

			const frameHandle = await shopperPage.waitForSelector(
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

		test( 'should throw an error that the card was declined due to invalid 3DS card', async () => {
			await shopper.fillCardDetails(
				shopperPage,
				config.cards[ 'declined-3ds' ]
			);
			await shopper.placeOrder( shopperPage );

			await shopper.confirmCardAuthentication( shopperPage, false );

			await waitForBanner(
				shopperPage,
				'We are unable to authenticate your payment method. Please choose a different payment method and try again.'
			);
		} );
	}
);
