/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { updateErrorForTimeline } from '../actions';
import { getTimeline } from '../resolvers';

describe( 'getTimeline resolver', () => {
	const intentId = 'pi_1';
	const traversalId = '../../wp/v2/users';
	const invalidId = '..';

	test( 'should request the timeline for the given intent', () => {
		const generator = getTimeline( intentId );

		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/timeline/pi_1' } )
		);
	} );

	test( 'should encode characters that would change the request', () => {
		const generator = getTimeline( traversalId );

		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/timeline/..%2F..%2Fwp%2Fv2%2Fusers',
			} )
		);
	} );

	test( 'should record an error for an invalid ID without a request', () => {
		const generator = getTimeline( invalidId );

		expect( generator.next().value ).toEqual(
			updateErrorForTimeline(
				'..',
				expect.objectContaining( {
					code: 'wcpay_invalid_path_segment',
				} )
			)
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );
