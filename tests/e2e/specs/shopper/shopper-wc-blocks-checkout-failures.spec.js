/**
 * External dependencies
 */
import config from 'config';

const { shopper, merchant, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	shopperWCP,
	merchantWCP,
	describeif,
	RUN_WC_BLOCKS_TESTS,
} from '../../utils';

const shippingDetails = config.get( 'addresses.customer.shipping' );
const productName = config.get( 'products.simple.name' );

import {
	fillCardDetailsWCB,
	clearWCBCardDetails,
	confirmCardAuthentication,
} from '../../utils/payments';

describeif( RUN_WC_BLOCKS_TESTS )(
	'WooCommerce Blocks > Checkout failures',
	() => {
		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.addNewPageCheckoutWCB();
			await merchant.logout();
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillShippingDetailsWCB( shippingDetails );
		} );

		afterEach( async () => {
			// Clear card details for the next test
			await clearWCBCardDetails();
		} );

		afterAll( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopper.emptyCart();
		} );

		it( 'should throw an error that the card was simply declined', async () => {
			const declinedCard = config.get( 'cards.declined' );
			await fillCardDetailsWCB( page, declinedCard );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await page.waitForSelector( 'div.wc-block-components-notices' );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: 'Your card was declined.',
				}
			);
		} );

		it( 'should throw an error that the card expiration date is in the past', async () => {
			const cardInvalidExpDate = config.get( 'cards.invalid-exp-date' );
			await fillCardDetailsWCB( page, cardInvalidExpDate );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: "Your card's expiration year is in the past.",
				}
			);
		} );

		it( 'should throw an error that the card CVV number is invalid', async () => {
			const cardInvalidCVV = config.get( 'cards.invalid-cvv-number' );
			await fillCardDetailsWCB( page, cardInvalidCVV );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await expect(
				page
			).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{ text: "Your card's security code is incomplete." }
			);
		} );

		it( 'should throw an error that the card was declined due to insufficient funds', async () => {
			const cardInsufficientFunds = config.get( 'cards.declined-funds' );
			await fillCardDetailsWCB( page, cardInsufficientFunds );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await page.waitForSelector( 'div.wc-block-components-notices' );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: 'Your card has insufficient funds.',
				}
			);
		} );

		it( 'should throw an error that the card was declined due to expired card', async () => {
			const cardExpired = config.get( 'cards.declined-expired' );
			await fillCardDetailsWCB( page, cardExpired );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await page.waitForSelector( 'div.wc-block-components-notices' );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: 'Your card has expired.',
				}
			);
		} );

		it( 'should throw an error that the card was declined due to incorrect CVC number', async () => {
			const cardIncorrectCVC = config.get( 'cards.declined-cvc' );
			await fillCardDetailsWCB( page, cardIncorrectCVC );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await page.waitForSelector( 'div.wc-block-components-notices' );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: "Your card's security code is incorrect.",
				}
			);
		} );

		it( 'should throw an error that the card was declined due to processing error', async () => {
			const cardProcessingError = config.get(
				'cards.declined-processing'
			);
			await fillCardDetailsWCB( page, cardProcessingError );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await uiUnblocked();
			await page.waitForSelector( 'div.wc-block-components-notices' );
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text:
						'An error occurred while processing your card. Try again in a little bit.',
				}
			);
		} );

		it( 'should throw an error that the card was declined due to incorrect card number', async () => {
			const cardIncorrectNumber = config.get(
				'cards.declined-incorrect'
			);
			await fillCardDetailsWCB( page, cardIncorrectNumber );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			// Verify the error message
			await expect( page ).toMatchElement(
				'div.wc-block-components-notices > div > div.components-notice__content',
				{
					text: 'Your card number is invalid.',
				}
			);
		} );

		it( 'should throw an error that the card was declined due to invalid 3DS card', async () => {
			const declinedCard = config.get( 'cards.declined-3ds' );
			await fillCardDetailsWCB( page, declinedCard );
			await expect( page ).toClick( 'button > span', {
				text: 'Place Order',
			} );
			await confirmCardAuthentication( page, '3DS' );
			await page.waitForSelector( 'div.wc-block-components-notices' );
			const declined3dsCardError = await page.$eval(
				'div.wc-block-components-notices > div > div.components-notice__content',
				( el ) => el.innerText
			);
			await expect( page ).toMatch(
				declined3dsCardError,
				'Your card was declined.'
			);
		} );
	}
);
