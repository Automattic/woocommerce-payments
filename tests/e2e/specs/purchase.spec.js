/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow, uiUnblocked } from '../utils';
import { fillCardDetails } from '../utils/payments';

describe( 'Successful purchase', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );
	} );

	it( 'successful purchase', async () => {
		await CustomerFlow.goToShop();
		await CustomerFlow.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await CustomerFlow.goToCheckout();
		await uiUnblocked();
		await CustomerFlow.fillBillingDetails(
			config.get( 'addresses.customer.billing' )
		);
		await uiUnblocked();
		await expect( page ).toClick(
			'.wc_payment_method.payment_method_woocommerce_payments'
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await CustomerFlow.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );
} );
