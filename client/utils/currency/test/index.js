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
} );
