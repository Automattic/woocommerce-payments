/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { isTransactionFullyRefunded, isTransactionPartiallyRefunded, isTransactionRefunded } from '../';

describe( 'Transaction utilities', () => {
	test( 'should classify a fully refunded transaction', () => {
		// eslint-disable-next-line camelcase
		const transaction = { amount: 1500, source: { refunded: true, amount_refunded: 1500 } };
		expect( isTransactionFullyRefunded( transaction ) ).toEqual( true );
		expect( isTransactionPartiallyRefunded( transaction ) ).toEqual( false );
		expect( isTransactionRefunded( transaction ) ).toEqual( true );
	} );

	test( 'should classify a partially refunded transaction', () => {
		// eslint-disable-next-line camelcase
		const transaction = { amount: 1500, source: { refunded: true, amount_refunded: 1200 } };
		expect( isTransactionFullyRefunded( transaction ) ).toEqual( false );
		expect( isTransactionPartiallyRefunded( transaction ) ).toEqual( true );
		expect( isTransactionRefunded( transaction ) ).toEqual( true );
	} );

	test( 'should classify a non refunded transaction', () => {
		// eslint-disable-next-line camelcase
		const transaction = { amount: 1500, source: { refunded: false, amount_refunded: 0 } };
		expect( isTransactionFullyRefunded( transaction ) ).toEqual( false );
		expect( isTransactionPartiallyRefunded( transaction ) ).toEqual( false );
		expect( isTransactionRefunded( transaction ) ).toEqual( false );
	} );
} );
