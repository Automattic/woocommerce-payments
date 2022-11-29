/**
 * External dependencies
 */
import config from 'config';

const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';

const cards = [ [ 'basic', config.get( 'cards.basic' ) ] ];

const sepaPaymentMethod = '#inspector-checkbox-control-8';

describe( 'Add and delete the card with UPE method enabled', () => {
	describe.each( cards )(
		'when UPE payment method is enabled',
		( cardType, card ) => {
			beforeAll( async () => {
				await merchant.login();
				await merchantWCP.activateUpe();
				// enable SEPA
				await merchantWCP.enablePaymentMethod( sepaPaymentMethod );
				await merchant.logout();
				await shopper.login();
				await shopperWCP.changeAccountCurrencyTo( 'EUR' );
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

			it( 'should save the card', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.addNewPaymentMethod( cardType, card );
				await expect( page ).toMatch(
					'Payment method successfully added'
				);
			} );

			it( 'should delete the card', async () => {
				await shopperWCP.goToPaymentMethods();
				await shopperWCP.deleteSavedPaymentMethod( card.label );
				await expect( page ).toMatch( 'Payment method deleted' );
			} );
		}
	);
} );
