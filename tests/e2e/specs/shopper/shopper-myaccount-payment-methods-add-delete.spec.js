/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../utils/flows';

const cards = config.get( 'cards' );
const validCards = Object.entries( cards ).filter( ( [ cardType ] ) =>
	[
		'basic',
		'3ds',
		'3ds2',
		'disputed-fraudulent',
		'disputed-unreceived',
	].includes( cardType )
);

describe( 'Payment Methods', () => {
	beforeAll( async () => {
		await shopper.login();
		await shopperWCP.goToPaymentMethods();
	} );

	afterAll( async () => {
		await shopperWCP.logout();
	} );

	describe.each( validCards )( 'when using a %s card', ( cardType, card ) => {
		const { label } = card;
		const { month, year } = card.expires;

		it( 'should add the card as a new payment method', async () => {
			// mytodo handle the 20-sec interval
			await shopperWCP.addNewPaymentMethod( cardType, card );

			expect( page ).toMatch( 'Payment method successfully added' );
			expect( page ).toMatch( label );
			expect( page ).toMatch( `${ month }/${ year }` );
		} );

		it( 'should allow deleting of the card', async () => {
			await shopperWCP.deleteSavedPaymentMethod( label );
			expect( page ).toMatch( 'Payment method deleted.' );
			expect( page ).not.toMatch( label );
		} );
	} );

	// mytodo describe 'Make default'
	// mytodo describe 'Cannot add invalid cards'
	// mytodo describe 'field validations'
} );
