/**
 * Internal dependencies
 */
import { getMissingCurrenciesTooltipMessage } from '../missing-currencies-message';

describe( 'getMissingCurrenciesTooltipMessage', () => {
	it( 'returns correct string with the given LPM label and currency list', () => {
		expect( getMissingCurrenciesTooltipMessage( 'x', [ 'EUR' ] ) ).toBe(
			'x requires the EUR currency. In order to enable the payment method, you must add this currency to your store.'
		);
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN' ] )
		).toBe(
			'x requires the EUR and PLN currencies. In order to enable the payment method, you must add these currencies to your store.'
		);
		expect(
			getMissingCurrenciesTooltipMessage( 'x', [ 'EUR', 'PLN', 'TRY' ] )
		).toBe(
			'x requires the EUR, PLN, and TRY currencies. ' +
				'In order to enable the payment method, you must add these currencies to your store.'
		);
	} );
} );
