/**
 * Internal dependencies
 */
import { isUsingSavedPaymentMethod } from '../upe';

describe( 'UPE checkout', () => {
	describe( 'isUsingSavedPaymentMethod', () => {
		let container;

		beforeAll( () => {
			container = document.createElement( 'div' );
			container.innerHTML = `
				<label>
					<input type="radio" id="wc-woocommerce_payments-payment-token-new" value="new">
					Use a new payment method
				</label>
			`;
			document.body.appendChild( container );
		} );

		afterAll( () => {
			document.body.removeChild( container );
			container = null;
		} );

		test( 'new CC is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments-payment-token-new'
			);
			input.checked = true;

			expect( isUsingSavedPaymentMethod() ).toBe( false );
		} );

		test( 'saved CC is selected', () => {
			const input = document.querySelector(
				'#wc-woocommerce_payments-payment-token-new'
			);
			input.checked = false;

			expect( isUsingSavedPaymentMethod() ).toBe( true );
		} );
	} );
} );
