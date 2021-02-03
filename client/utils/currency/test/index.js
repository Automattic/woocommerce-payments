/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import * as utils from 'utils/currency';

describe( 'Currency utilities', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			zeroDecimalCurrencies: [ 'vnd', 'jpy' ],
		};
	} );

	test( 'format supported currency', () => {
		expect( utils.formatCurrency( 1000, 'USD' ) ).toEqual( '$10.00' );
	} );

	test( 'format unsupported currency', () => {
		expect( utils.formatCurrency( 1000, 'AUD' ) ).toEqual( 'AUD 10.00' );
		expect( utils.formatCurrency( 1000, 'JPY' ) ).toEqual( 'JPY 1000' );
	} );

	test.each`
		source                                   | target                                 | expected
		${ { currency: 'EUR', amount: 1242 } }   | ${ { currency: 'USD', amount: 1484 } } | ${ '€1,00 → $1.19488: $14.84' }
		${ { currency: 'INR', amount: 131392 } } | ${ { currency: 'USD', amount: 1779 } } | ${ '₹1.00 → $0.013539: $17.79' }
		${ { currency: 'RUB', amount: 136746 } } | ${ { currency: 'USD', amount: 1777 } } | ${ '1,00₽ → $0.012996: $17.77' }
	`(
		'format FX string $source.currency -> $target.currency',
		( { source, target, expected } ) => {
			expect( utils.formatFX( source, target ) ).toBe( expected );
		}
	);
} );
