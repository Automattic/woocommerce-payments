/**
 * External dependencies
 */
import config from 'config';

const {
	shopper,
	createSimpleProduct,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );

import {
	fillCardDetails,
	setupProductCheckout,
	confirmCardAuthentication,
} from '../../utils/payments';

describe( 'Shopper > Checkout > Failures with various cards', () => {
	beforeAll( async () => {
		await createSimpleProduct();
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
	} );

	afterEach( async () => {
		await page.reload();
	} );

	it( 'should throw an error that the card was simply declined', async () => {
		const declinedCard = config.get( 'cards.declined' );
		await fillCardDetails( page, declinedCard );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: 'Error: Your card was declined.' }
		);
	} );

	it( 'should throw an error that the card was declined due to insufficient funds', async () => {
		const cardInsufficientFunds = config.get( 'cards.declined-funds' );
		await fillCardDetails( page, cardInsufficientFunds );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: 'Error: Your card has insufficient funds.' }
		);
	} );

	it( 'should throw an error that the card was declined due to expired card', async () => {
		const cardExpired = config.get( 'cards.declined-expired' );
		await fillCardDetails( page, cardExpired );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: 'Error: Your card has expired.' }
		);
	} );

	it( 'should throw an error that the card was declined due to incorrect CVC number', async () => {
		const cardIncorrectCVC = config.get( 'cards.declined-cvc' );
		await fillCardDetails( page, cardIncorrectCVC );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: "Error: Your card's security code is incorrect." }
		);
	} );

	it( 'should throw an error that the card was declined due to processing error', async () => {
		const cardProcessingError = config.get( 'cards.declined-processing' );
		await fillCardDetails( page, cardProcessingError );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect( page ).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{
				text:
					'Error: An error occurred while processing your card. Try again in a little bit.',
			}
		);
	} );

	it( 'should throw an error that the card was declined due to incorrect card number', async () => {
		const cardIncorrectNumber = config.get( 'cards.declined-incorrect' );
		await fillCardDetails( page, cardIncorrectNumber );

		// Verify the error message
		await expect( page ).toMatchElement(
			'div#wcpay-errors > ul.woocommerce-error > li',
			{
				text: 'Your card number is invalid.',
			}
		);

		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error',
			{ text: 'Your card number is invalid.' }
		);
	} );

	it( 'should throw an error that the card was declined due to invalid 3DS card', async () => {
		const declinedCard = config.get( 'cards.declined-3ds' );
		await fillCardDetails( page, declinedCard );
		await expect( page ).toClick( '#place_order' );
		await confirmCardAuthentication( page, '3DS' );
		await page.waitForSelector( 'ul.woocommerce-error' );
		const declined3dsCardError = await page.$eval(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error',
			( el ) => el.innerText
		);
		await expect( page ).toMatch(
			declined3dsCardError,
			'Error: Your card was declined.'
		);

		// Clear the cart at the end
		await shopper.emptyCart();
	} );
} );
