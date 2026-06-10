/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	updateErrorForReportsFees,
	updateErrorForReportsFeesSummary,
	updateReportsFees,
	updateReportsFeesSummary,
} from '../actions';
import { getReportsFees, getReportsFeesSummary } from '../resolvers';
import { getUserTimeZone } from 'jest-utils/timezone';

const errorResponse = { code: 'error' };

const paginationQuery = {
	paged: 1,
	perPage: 25,
	orderby: 'date',
	order: 'desc',
};

const filterQuery = {
	match: 'all',
	dateBefore: '2026-04-01 00:00:00',
	dateAfter: '2026-04-30 23:59:59',
	dateBetween: [ '2026-04-01 00:00:00', '2026-04-30 23:59:59' ],
	paymentMethodType: 'card',
	type: 'charge',
	orderId: '123',
	depositId: 'po_123',
	customerEmail: 'shopper@example.com',
	search: [ 'txn_123' ],
};

const expectedFilterParams = {
	match: 'all',
	date_before: '2026-04-02 03:59:59',
	date_after: '2026-04-30 04:00:00',
	'date_between[0]': '2026-04-01 04:00:00',
	'date_between[1]': '2026-05-01 03:59:59',
	payment_method_type: 'card',
	'type[0]': 'charge',
	order_id: '123',
	deposit_id: 'po_123',
	'search[0]': 'txn_123',
	user_timezone: getUserTimeZone(),
};

const expectApiFetchPath = ( control, expectedPath, expectedParams ) => {
	expect( control ).toEqual(
		apiFetch( {
			path: expect.any( String ),
		} )
	);

	const url = new URL( control.request.path, 'https://example.test' );

	expect( url.pathname ).toBe( expectedPath );
	expect( Object.fromEntries( url.searchParams.entries() ) ).toEqual(
		expectedParams
	);
	expect( url.searchParams.has( 'customer_email' ) ).toBe( false );
};

describe( 'getReportsFees resolver', () => {
	const successfulResponse = [ { transaction_id: 'txn_123' } ];
	const query = { ...paginationQuery, ...filterQuery };
	const expectedParams = {
		page: '1',
		per_page: '25',
		sort: 'date',
		direction: 'desc',
		...expectedFilterParams,
	};

	test( 'updates state with report fee rows on success', () => {
		const generator = getReportsFees( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/fees',
			expectedParams
		);
		expect( generator.next( successfulResponse ).value ).toEqual(
			updateReportsFees( query, successfulResponse )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates state with the error on failure', () => {
		const generator = getReportsFees( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/fees',
			expectedParams
		);
		expect( generator.throw( errorResponse ).value ).toEqual(
			controls.dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
		expect( generator.next().value ).toEqual(
			updateErrorForReportsFees( query, errorResponse )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );

describe( 'getReportsFeesSummary resolver', () => {
	const successfulResponse = { count: 1, total: 1000, fees: 120 };
	const query = filterQuery;

	test( 'updates state with reports fees summary data on success', () => {
		const generator = getReportsFeesSummary( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/fees/summary',
			expectedFilterParams
		);
		expect( generator.next( successfulResponse ).value ).toEqual(
			updateReportsFeesSummary( query, successfulResponse )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates state with the summary error on failure', () => {
		const generator = getReportsFeesSummary( query );

		expectApiFetchPath(
			generator.next().value,
			'/wc/v3/payments/reports/fees/summary',
			expectedFilterParams
		);
		expect( generator.throw( errorResponse ).value ).toEqual(
			controls.dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
		expect( generator.next().value ).toEqual(
			updateErrorForReportsFeesSummary( query, errorResponse )
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );
} );
