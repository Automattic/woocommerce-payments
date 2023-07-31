const { uiUnblocked, shopper } = require( '@woocommerce/e2e-utils' );
const {
	setupProductCheckout,
	fillCardDetails,
	fillCardDetailsPayForOrder,
} = require( '../../../utils/payments' );
const config = require( 'config' );
const { shopperWCP } = require( '../../../utils' );

describe( 'Shopper > Pay for Order', () => {
	beforeAll( async () => {
		await shopper.login();
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
	} );

	afterAll( async () => {
		await shopper.logout();
	} );

	it( 'should be able to pay for a failed order', async () => {
		// try to pay with a declined card
		const declinedCard = config.get( 'cards.declined' );
		await shopperWCP.selectNewPaymentMethod();
		await fillCardDetails( page, declinedCard );
		await expect( page ).toClick( '#place_order' );
		await uiUnblocked();
		await expect(
			page
		).toMatchElement(
			'div.woocommerce-NoticeGroup > ul.woocommerce-error > li',
			{ text: 'Error: Your card was declined.' }
		);

		// after the card has been declined, go to the order page and pay with a basic card
		await shopperWCP.goToOrders();

		const payButtons = await page.$$( '.woocommerce-button.button.pay' );
		const payButton = payButtons.find(
			async ( button ) =>
				( await page.evaluate(
					( elem ) => elem.innerText,
					button
				) ) === 'Pay'
		);
		await payButton.click();
		const card = config.get( 'cards.basic' );
		await fillCardDetailsPayForOrder( page, card );
		await expect( page ).toClick( 'button', { text: 'Pay for order' } );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
