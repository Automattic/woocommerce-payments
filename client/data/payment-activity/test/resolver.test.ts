/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { updatePaymentActivity } from '../actions';
import { getPaymentActivityData } from '../resolvers';

const query = {
	date_start: '2020-04-29T04:00:00',
	date_end: '2020-04-29T03:59:59',
	timezone: '+2:30',
};

describe( 'getPaymentActivityData resolver', () => {
	const successfulResponse: any = { amount: 3000 };
	const expectedQueryString =
		'date_start=2020-04-29T04%3A00%3A00&date_end=2020-04-29T03%3A59%3A59&timezone=%2B2%3A30';
	const errorResponse = new Error(
		'Error retrieving payment activity data.'
	);

	let generator: any = null;

	beforeEach( () => {
		generator = getPaymentActivityData( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/reporting/payment_activity?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with payment activity data', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updatePaymentActivity( successfulResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
		} );
	} );
} );
