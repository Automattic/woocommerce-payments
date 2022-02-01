/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../../utils/flows';
const { fillCardDetails } = require( '../../../utils/payments' );

const cards = Object.entries( config.get( 'cards' ) );
const invalidCards = cards.filter( ( [ cardType ] ) =>
	cardType.includes( 'declined' )
);

describe( 'Payment Methods', () => {
	beforeAll( async () => {
		await shopper.login();
	} );

	afterAll( async () => {
		await shopperWCP.logout();
	} );

	describe.each( invalidCards )(
		'when attempting to add a %s card',
		( cardType, card ) => {
			beforeEach( async () => {
				// Click the 'Add payment method` button in the 'Payment methods' page.
				await shopperWCP.goToPaymentMethods();
				await expect( page ).toClick( 'a', {
					text: 'Add payment method',
				} );
				await page.waitForNavigation( {
					waitUntil: 'networkidle0',
				} );
			} );

			it( 'should not add the card', async () => {
				const { label } = card;

				await fillCardDetails( page, card );
				await expect( page ).toClick( 'button', {
					text: 'Add payment method',
				} );
				await expect( page ).toMatchElement( '.woocommerce-error', {
					timeout: 30000,
				} );

				await shopperWCP.goToPaymentMethods();
				await expect( page ).not.toMatch( label );
			} );
		}
	);

	it( 'should not show error when adding payment method on another gateway', async () => {
		await shopperWCP.goToPaymentMethods();
		await expect( page ).toClick( 'a', {
			text: 'Add payment method',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		//This will simulate selecting other payment gateway
		await page.$eval( 'input[name="payment_method"]:checked', ( input ) => {
			input.checked = false;
		} );

		await expect( page ).toClick( 'button', {
			text: 'Add payment method',
		} );

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		await expect( page ).not.toMatchElement( '.woocommerce-error', {
			timeout: 3000,
		} );
	} );
} );
