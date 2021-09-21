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
		expect( utils.formatCurrency( 1000, 'AUD' ) ).toEqual( '$10.00' );
		expect( utils.formatCurrency( 1000, 'JPY' ) ).toEqual( '¥1,000' );
		expect( utils.formatCurrencyName( 'jpy' ) ).toEqual( 'JPY' );
	} );

	test.each`
		source                                   | target                                 | expected
		${ { currency: 'EUR', amount: 1242 } }   | ${ { currency: 'USD', amount: 1484 } } | ${ '1,00 EUR → 1.19485 USD: $14.84 USD' }
		${ { currency: 'CHF', amount: 1500 } }   | ${ { currency: 'USD', amount: 1675 } } | ${ '1.00 CHF → 1.11667 USD: $16.75 USD' }
		${ { currency: 'GBP', amount: 1800 } }   | ${ { currency: 'USD', amount: 2439 } } | ${ '1.00 GBP → 1.355 USD: $24.39 USD' }
		${ { currency: 'INR', amount: 131392 } } | ${ { currency: 'USD', amount: 1779 } } | ${ '1.00 INR → 0.01354 USD: $17.79 USD' }
		${ { currency: 'RUB', amount: 136746 } } | ${ { currency: 'USD', amount: 1777 } } | ${ '1,00 RUB → 0.012995 USD: $17.77 USD' }
		${ { currency: 'JPY', amount: 1894 } }   | ${ { currency: 'USD', amount: 1786 } } | ${ '1 JPY → 0.00943 USD: $17.86 USD' }
	`(
		'format FX string $source.currency -> $target.currency',
		( { source, target, expected } ) => {
			expect( utils.formatFX( source, target ) ).toBe( expected );
		}
	);
} );
