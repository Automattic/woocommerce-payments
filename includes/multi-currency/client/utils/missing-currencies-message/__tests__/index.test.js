/**
 * Internal dependencies
 */
import { getMissingCurrenciesTooltipMessage } from 'multi-currency/utils/missing-currencies-message';

describe( 'getMissingCurrenciesTooltipMessage', () => {
	it( 'returns correct string for a single currency', () => {
		expect( getMissingCurrenciesTooltipMessage( 'x', [ 'EUR' ] ) ).toBe(
			'x requires the EUR currency. In order to enable the payment method, you must add this currency to your store.'
		);
	} );

	it( 'returns correct string with "or" for two currencies', () => {
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN' ] )
		).toBe(
			'x requires at least one of the following currencies: EUR or PLN. You must add at least one of these currencies to your store.'
		);
	} );

	it( 'returns correct string with "or" for three currencies', () => {
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN', 'TRY' ] )
		).toBe(
			'x requires at least one of the following currencies: EUR, PLN, or TRY. You must add at least one of these currencies to your store.'
		);
	} );
} );
