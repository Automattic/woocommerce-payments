/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { updateErrorForFiles } from '../actions';
import { getFile } from '../resolvers';

describe( 'getFile resolver', () => {
	const fileId = 'file_1';
	const traversalId = 'file_1/../../x?y';
	const invalidId = '..';

	test( 'should request the details for the given file', () => {
		const generator = getFile( fileId );

		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/file/file_1/details' } )
		);
	} );

	test( 'should encode characters that would change the request', () => {
		const generator = getFile( traversalId );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/file/file_1%2F..%2F..%2Fx%3Fy/details',
			} )
		);
	} );

	test( 'should record an error for an invalid ID without a request', () => {
		const generator = getFile( invalidId );

		expect( generator.next().value ).toEqual(
			controls.dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
		expect( generator.next().value ).toEqual(
			updateErrorForFiles(
				'..',
				expect.objectContaining( {
					code: 'wcpay_invalid_path_segment',
				} )
			)
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );
