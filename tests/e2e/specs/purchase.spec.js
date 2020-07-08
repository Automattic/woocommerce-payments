/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow, uiUnblocked } from '../utils';
import { fillCardDetails } from '../utils/payments';

const TIMEOUT = 100000;

describe( 'Successful purchase', () => {
	beforeAll( async () => {
		// Increase default value to avoid test failing due to timeouts.
		page.setDefaultTimeout( 20000 );
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );
	} );

	it( 'successful purchase', async () => {
		await CustomerFlow.goToShop();
		await CustomerFlow.addToCartFromShopPage( config.get( 'products.simple.name' ) );
		await CustomerFlow.goToCheckout();
		await uiUnblocked();
		await CustomerFlow.fillBillingDetails(
			config.get( 'addresses.customer.billing' )
		);
		await uiUnblocked();
		await expect( page ).toClick( '.wc_payment_method.payment_method_woocommerce_payments' );
		const card = {
			number: '4242424242424242',
			expires: {
				month: '02',
				year: '24',
			},
			cvc: '424',
		};
		await fillCardDetails( page, card );
		await CustomerFlow.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	}, TIMEOUT );
} );

