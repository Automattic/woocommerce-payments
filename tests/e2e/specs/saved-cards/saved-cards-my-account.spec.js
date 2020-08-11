/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow, uiUnblocked } from '../../utils';
import {
	fillCardDetails,
	confirmCardAuthentication,
} from '../../utils/payments';

const cards = [
	[ 'basic', config.get( 'cards.basic' ) ],
	[ '3DS2', config.get( 'cards.3ds2' ) ],
];

describe( 'Saved cards ', () => {
	describe.each( cards )(
		'when using a %s card added through my account',
		( cardType, card ) => {
			beforeAll( async () => {
				await CustomerFlow.login();
			} );

			afterAll( async () => {
				await CustomerFlow.logout();
			} );

			it( 'should save the card', async () => {
				await CustomerFlow.goToPaymentMethods();
				await expect( page ).toClick( 'a', {
					text: 'Add payment method',
				} );
				await page.waitForNavigation( {
					waitUntil: 'networkidle0',
				} );
				await fillCardDetails( page, card );
				await expect( page ).toClick( 'button', {
					text: 'Add payment method',
				} );

				if ( 'basic' !== cardType ) {
					await confirmCardAuthentication( page, cardType );
				}
				await page.waitForNavigation( {
					waitUntil: 'networkidle0',
				} );

				await expect( page ).toMatch(
					'Payment method successfully added'
				);
			} );

			it( 'should process a payment with the saved card', async () => {
				await setupProductCheckout();
				await CustomerFlow.selectSavedPaymentMethod(
					`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
				);

				if ( 'basic' === cardType ) {
					await CustomerFlow.placeOrder();
				} else {
					await expect( page ).toClick( '#place_order' );
					await confirmCardAuthentication( page, cardType );
					await page.waitForNavigation( {
						waitUntil: 'networkidle0',
					} );
				}

				await expect( page ).toMatch( 'Order received' );
			} );

			it( 'should delete the card', async () => {
				await CustomerFlow.goToPaymentMethods();
				await CustomerFlow.deleteSavedPaymentMethod( card.label );
				await expect( page ).toMatch( 'Payment method deleted' );
			} );
		}
	);

	async function setupProductCheckout() {
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
	}
} );
