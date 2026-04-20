/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Documents reducer tests', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDocuments = [
		{
			document_id: 'vat_invoice_12345',
			date: '2022-03-15 12:00:00',
			type: 'vat_invoice',
			period_from: '2022-03-01 00:00:00',
			period_to: '2022-03-31 23:59:59',
		},
		{
			document_id: 'vat_invoice_54321',
			date: '2022-02-15 12:00:00',
			type: 'vat_invoice',
			period_from: '2022-02-01 00:00:00',
			period_to: '2022-02-28 23:59:59',
		},
	];
	const mockSummary = {
		count: 2,
	};

	const emptyState = {};
	const filledState = {
		[ getResourceId( mockQuery ) ]: {
			data: mockDocuments,
		},
		summary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockSummary,
			},
		},
	};

	test( 'Unrelated action is ignored', () => {
		expect( reducer( emptyState, { type: 'WRONG-TYPE' } ) ).toBe(
			emptyState
		);
		expect( reducer( filledState, { type: 'WRONG-TYPE' } ) ).toBe(
			filledState
		);
	} );

	test( 'New documents reduced correctly', () => {
		// Set up mock data
		const expected = {
			[ getResourceId( mockQuery ) ]: {
				data: mockDocuments,
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_DOCUMENTS,
			data: mockDocuments,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Documents updated correctly on updated info', () => {
		const newDocuments = [ ...mockDocuments, ...mockDocuments ];

		const expected = {
			...filledState,
			[ getResourceId( mockQuery ) ]: {
				data: newDocuments,
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_DOCUMENTS,
			data: newDocuments,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'New documents summary reduced correctly', () => {
		const expected = {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockSummary,
				},
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_DOCUMENTS_SUMMARY,
			data: mockSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );
} );
