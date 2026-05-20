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

describe( 'getReportsFees resolver', () => {
	const successfulResponse = [ { transaction_id: 'txn_123' } ];
	const query = { ...paginationQuery, ...filterQuery };
	const expectedQueryString =
		'page=1&per_page=25&sort=date&direction=desc' +
		'&match=all&date_before=2026-04-02%2003%3A59%3A59&date_after=2026-04-30%2004%3A00%3A00' +
		'&date_between%5B0%5D=2026-04-01%2004%3A00%3A00&date_between%5B1%5D=2026-05-01%2003%3A59%3A59' +
		'&payment_method_type=card&type=charge&order_id=123&deposit_id=po_123&customer_email=shopper%40example.com' +
		'&search%5B0%5D=txn_123' +
		`&user_timezone=${ encodeURIComponent( getUserTimeZone() ) }`;
	let generator = null;

	beforeEach( () => {
		generator = getReportsFees( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/reports/fees?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates state with report fee rows on success', () => {
		expect( generator.next( successfulResponse ).value ).toEqual(
			updateReportsFees( query, successfulResponse )
		);
	} );

	test( 'updates state with the error on failure', () => {
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
	} );
} );

describe( 'getReportsFeesSummary resolver', () => {
	const successfulResponse = { count: 1, total: 1000, fees: 120 };
	const query = filterQuery;
	const expectedQueryString =
		'match=all&date_before=2026-04-02%2003%3A59%3A59&date_after=2026-04-30%2004%3A00%3A00' +
		'&date_between%5B0%5D=2026-04-01%2004%3A00%3A00&date_between%5B1%5D=2026-05-01%2003%3A59%3A59' +
		'&payment_method_type=card&type=charge&order_id=123&deposit_id=po_123&customer_email=shopper%40example.com' +
		'&search%5B0%5D=txn_123' +
		`&user_timezone=${ encodeURIComponent( getUserTimeZone() ) }`;
	let generator = null;

	beforeEach( () => {
		generator = getReportsFeesSummary( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/reports/fees/summary?${ expectedQueryString }`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates state with reports fees summary data on success', () => {
		expect( generator.next( successfulResponse ).value ).toEqual(
			updateReportsFeesSummary( query, successfulResponse )
		);
	} );

	test( 'updates state with the summary error on failure', () => {
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
	} );
} );
