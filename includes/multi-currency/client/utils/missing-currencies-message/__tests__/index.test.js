/**
 * Internal dependencies
 */
import { getMissingCurrenciesTooltipMessage } from 'multi-currency/utils/missing-currencies-message';

describe( 'getMissingCurrenciesTooltipMessage', () => {
	it( 'returns correct string for a single currency', () => {
		expect( getMissingCurrenciesTooltipMessage( 'x', [ 'EUR' ] ) ).toBe(
			'x requires the EUR currency. Add EUR to your store to offer this payment method.'
		);
	} );

	it( 'returns correct string with "or" for two currencies', () => {
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN' ] )
		).toBe(
			'x requires at least one of the following currencies: EUR or PLN. Add at least one of these currencies to your store to offer this payment method.'
		);
	} );

	it( 'returns correct string with "or" for three currencies', () => {
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN', 'TRY' ] )
		).toBe(
			'x requires at least one of the following currencies: EUR, PLN, or TRY. Add at least one of these currencies to your store to offer this payment method.'
		);
	} );
} );
