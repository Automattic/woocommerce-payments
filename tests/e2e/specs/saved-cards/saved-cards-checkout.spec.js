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

const TIMEOUT = 100000;

const cards = [
	[ 'basic', config.get( 'cards.basic' ) ],
	[ '3DS', config.get( 'cards.3ds' ) ],
];

describe( 'Saved cards ', () => {
	describe.each( cards )(
		'when using a %s card added on checkout',
		( cardType, card ) => {
			beforeAll( async () => {
				// Increase default value to avoid test failing due to timeouts.
				page.setDefaultTimeout( 30000 );
				// running the login flow takes more than the default timeout of 5 seconds,
				// so we need to increase it to run the login in the beforeAll hook
				jest.setTimeout( TIMEOUT );
				await CustomerFlow.login();
			} );

			afterAll( async () => {
				await CustomerFlow.logout();
			} );

			it(
				'should save the card',
				async () => {
					await setupProductCheckout();
					await CustomerFlow.selectNewPaymentMethod();
					await fillCardDetails( page, card );
					await CustomerFlow.toggleSavePaymentMethod();

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

					// validate that the payment method has been added to the customer.
					await CustomerFlow.goToPaymentMethods();
					await expect( page ).toMatch( card.label );
					await expect( page ).toMatch(
						`${ card.expires.month }/${ card.expires.year }`
					);
				},
				TIMEOUT
			);

			it(
				'should process a payment with the saved card',
				async () => {
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
				},
				TIMEOUT
			);

			it(
				'should delete the card',
				async () => {
					await CustomerFlow.goToPaymentMethods();
					await CustomerFlow.deleteSavedPaymentMethod( card.label );
					await expect( page ).toMatch( 'Payment method deleted' );
				},
				TIMEOUT
			);
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
