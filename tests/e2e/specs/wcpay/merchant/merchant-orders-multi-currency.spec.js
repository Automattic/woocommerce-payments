/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { merchantWCP, shopperWCP, uiLoaded } from '../../../utils';

const placeOrderWithCurrency = async ( currency ) => {
	try {
		await shopperWCP.goToShopWithCurrency( currency );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ config.get( 'products.simple.name' ), 1 ] ],
			currency
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		const url = await page.url();
		return url.match( /\/order-received\/(\d+)\// )[ 1 ];
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error(
			`Error placing order with currency ${ currency }: `,
			error
		);
		throw error;
	}
};

describe( 'Admin Multi-Currency Orders', () => {
	let wasMulticurrencyEnabled;
	const currenciesOrders = {
		USD: null,
		EUR: 115,
	};

	beforeAll( async () => {
		await merchant.login();
		wasMulticurrencyEnabled = await merchantWCP.activateMulticurrency();
		for ( const currency in currenciesOrders ) {
			await merchantWCP.addCurrency( currency );
		}
		await merchant.logout();

		await shopper.login();
		currenciesOrders.EUR = await placeOrderWithCurrency( 'EUR' );
		await shopper.logout();
		await merchant.login();
	} );

	afterAll( async () => {
		if ( ! wasMulticurrencyEnabled ) {
			await merchantWCP.deactivateMulticurrency();
		}
		await merchant.logout();
	} );

	it( 'order should display in shopper currency', async () => {
		await merchant.goToOrder( currenciesOrders.EUR );

		// Prices from order items table and confirm they are in the shopper currency
		const orderItems = await page.$$eval(
			'#woocommerce-order-items .woocommerce-Price-amount',
			( elements ) => elements.map( ( element ) => element.textContent )
		);

		orderItems.forEach( ( item ) => {
			expect( item ).toContain( '€' );
		} );
	} );

	it( 'transaction page shows shopper currency', async () => {
		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);
		await Promise.all( [
			page.goto( paymentDetailsLink, {
				waitUntil: 'networkidle0',
			} ),
			uiLoaded(),
		] );

		await expect( page ).toMatchElement(
			'.payment-details-summary__amount',
			{
				text: '€',
			}
		);
	} );

	it( 'transaction page shows converted merchant currency', async () => {
		// Confirm that transaction page shows payment details in merchant currency
		await expect( page ).toMatchElement(
			'.payment-details-summary__breakdown',
			{
				text: '$',
			}
		);

		// Confirm that transaction page shows fee in merchant currency
		const feesTextElement = await page.$x(
			"//div[@class='payment-details-summary__breakdown']/p[contains(text(), 'Fees')]"
		);
		const feesText = await page.evaluate(
			( element ) => element.textContent,
			feesTextElement[ 0 ]
		);
		expect( feesText ).toContain( '$' );
	} );
} );
