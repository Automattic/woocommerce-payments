/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { updateErrorForCardReaderStats } from '../actions';
import { getCardReaderStats } from '../resolvers';

describe( 'getCardReaderStats resolver', () => {
	const chargeId = 'ch_1';
	const transactionId = 'txn_1';
	const traversalId = '../../wp/v2/users';
	const invalidId = '..';

	test( 'should request the stats for the given transaction', () => {
		const generator = getCardReaderStats( chargeId, transactionId );

		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/readers/charges/txn_1' } )
		);
	} );

	test( 'should encode characters that would change the request', () => {
		const generator = getCardReaderStats( chargeId, traversalId );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/readers/charges/..%2F..%2Fwp%2Fv2%2Fusers',
			} )
		);
	} );

	test( 'should record an error for an invalid ID without a request', () => {
		const generator = getCardReaderStats( chargeId, invalidId );

		expect( generator.next().value ).toEqual(
			updateErrorForCardReaderStats(
				'ch_1',
				null,
				expect.objectContaining( {
					code: 'wcpay_invalid_path_segment',
				} )
			)
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );
