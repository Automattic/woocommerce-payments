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
		expect( utils.formatCurrencyName( 'usd' ) ).toEqual(
			'United States (US) dollar'
		);
	} );

	test( 'format unsupported currency', () => {
		expect( utils.formatCurrency( 1000, 'NOK' ) ).toEqual( 'NOK 10.00' );
		expect( utils.formatCurrency( 1000, 'JPY' ) ).toEqual( 'JPY 1000' );
		expect( utils.formatCurrencyName( 'jpy' ) ).toEqual( 'JPY' );
	} );

	test.each`
		source                                   | target                                 | expected
		${ { currency: 'EUR', amount: 1242 } }   | ${ { currency: 'USD', amount: 1484 } } | ${ 'EUR €1,00 → $1.19485: $14.84' }
		${ { currency: 'CHF', amount: 1500 } }   | ${ { currency: 'USD', amount: 1675 } } | ${ 'CHF 1.00 → $1.11667: $16.75' }
		${ { currency: 'GBP', amount: 1800 } }   | ${ { currency: 'USD', amount: 2439 } } | ${ 'GBP £1.00 → $1.355: $24.39' }
		${ { currency: 'INR', amount: 131392 } } | ${ { currency: 'USD', amount: 1779 } } | ${ 'INR ₹1.00 → $0.01354: $17.79' }
		${ { currency: 'RUB', amount: 136746 } } | ${ { currency: 'USD', amount: 1777 } } | ${ 'RUB 1,00₽ → $0.012995: $17.77' }
		${ { currency: 'JPY', amount: 1894 } }   | ${ { currency: 'USD', amount: 1786 } } | ${ 'JPY 1 → $0.00943: $17.86' }
	`(
		'format FX string $source.currency -> $target.currency',
		( { source, target, expected } ) => {
			expect( utils.formatFX( source, target ) ).toBe( expected );
		}
	);
} );
