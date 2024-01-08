/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../../utils/flows';
import {
	confirmCardAuthentication,
	setupProductCheckout,
} from '../../../utils/payments';

const cards = [
	[ 'basic', config.get( 'cards.basic' ) ],
	[ '3DS2', config.get( 'cards.3ds2' ) ],
];
const card2 = config.get( 'cards.basic2' );
const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 5000;

describe( 'Saved cards ', () => {
	let timeAdded;
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
				await shopperWCP.addNewPaymentMethod( cardType, card );
				await expect( page ).toMatch(
					'Payment method successfully added'
				);
				await expect( page ).toMatch(
					`${ card.expires.month }/${ card.expires.year }`
				);
				waitTwentySecondsSinceLastCardAdded();
			} );

			it( 'should process a payment with the saved card', async () => {
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' )
				);
				await shopperWCP.selectSavedPaymentMethod(
					`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
				);

				if ( cardType === 'basic' ) {
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

			it( 'should be able to set payment method as default', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.addNewPaymentMethod( 'basic2', card2 );
				// Take note of the time when we added this card
				timeAdded = Date.now();

				// Verify that the card was added
				await expect( page ).not.toMatch(
					'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
				);
				await expect( page ).toMatch(
					'Payment method successfully added'
				);
				await expect( page ).toMatch(
					`${ card2.expires.month }/${ card2.expires.year }`
				);
				await shopperWCP.setDefaultPaymentMethod( card2.label );
				// Verify that the card was set as default
				await expect( page ).toMatch(
					'This payment method was successfully set as your default.'
				);
			} );

			it( 'should delete the previously saved cards', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.deleteSavedPaymentMethod( card.label );
				await expect( page ).toMatch( 'Payment method deleted' );

				await shopperWCP.deleteSavedPaymentMethod( card2.label );
				await expect( page ).toMatch( 'Payment method deleted' );
			} );

			async function waitTwentySecondsSinceLastCardAdded() {
				// Make sure that at least 5s had already elapsed since the last card was added.
				// Otherwise, you will get the error message,
				// "You cannot add a new payment method so soon after the previous one."
				const timeTestFinished = Date.now();
				const elapsedWaitTime = timeTestFinished - timeAdded;
				const remainingWaitTime =
					MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS > elapsedWaitTime
						? MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS -
						  elapsedWaitTime
						: 0;

				await new Promise( ( r ) =>
					setTimeout( r, remainingWaitTime )
				);
			}
		}
	);
} );
