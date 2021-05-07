/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../utils';
import {
	fillCardDetails,
	confirmCardAuthentication,
	setupProductCheckout,
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
				await shopper.login();
			} );

			afterAll( async () => {
				await shopperWCP.logout();
			} );

			it( 'should save the card', async () => {
				await shopperWCP.goToPaymentMethods();
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
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await shopperWCP.selectSavedPaymentMethod(
					`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
				);

				if ( 'basic' === cardType ) {
					await shopper.placeOrder();
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
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.deleteSavedPaymentMethod( card.label );
				await expect( page ).toMatch( 'Payment method deleted' );
			} );
		}
	);
} );
