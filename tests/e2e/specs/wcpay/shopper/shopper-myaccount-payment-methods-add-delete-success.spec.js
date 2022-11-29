/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { merchant } from '@woocommerce/e2e-utils';

const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 20000;
const cards = Object.entries( config.get( 'cards' ) );
const validCards = cards.filter( ( [ cardType ] ) =>
	[ 'basic', '3ds', '3ds2' ].includes( cardType )
);
const sepaPaymentMethod = '#inspector-checkbox-control-8';

describe( 'Payment Methods', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.activateUpe();
		// enable SEPA
		await merchantWCP.enablePaymentMethod( sepaPaymentMethod );
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
		await shopperWCP.goToPaymentMethods();
	} );

	afterAll( async () => {
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
		await shopperWCP.logout();
		await merchant.login();
		//disable SEPA
		await merchantWCP.disablePaymentMethod( sepaPaymentMethod );
		await merchantWCP.deactivateUpe();
		await merchant.logout();
	} );

	describe.each( validCards )( 'when using a %s card', ( cardType, card ) => {
		const { label } = card;
		const { month, year } = card.expires;
		let timeAdded;

		it( 'should add the card as a new payment method', async () => {
			await shopperWCP.addNewPaymentMethod( cardType, card );

			// Take note of the time when we added this card
			timeAdded = Date.now();

			// Verify that the card was added
			await expect( page ).not.toMatch(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			);
			await expect( page ).toMatch( 'Payment method successfully added' );
			await expect( page ).toMatch( label );
			await expect( page ).toMatch( `${ month }/${ year }` );
		} );

		it( 'should be able to delete the card', async () => {
			await shopperWCP.deleteSavedPaymentMethod( label );
			await expect( page ).toMatch( 'Payment method deleted.' );
		} );

		afterAll( async () => {
			// Make sure that at least 20s had already elapsed since the last card was added.
			// Otherwise, you will get the error message,
			// "You cannot add a new payment method so soon after the previous one."
			const timeTestFinished = Date.now();
			const elapsedWaitTime = timeTestFinished - timeAdded;
			const remainingWaitTime =
				MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS > elapsedWaitTime
					? MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS - elapsedWaitTime
					: 0;

			await new Promise( ( r ) => setTimeout( r, remainingWaitTime ) );
		} );
	} );
} );
