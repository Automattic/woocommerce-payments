/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import {
	getDocuments,
	getDocumentsError,
	getDocumentsSummary,
	getDocumentsSummaryError,
} from '../selectors';

describe( 'Documents selectors', () => {
	// Mock objects.
	const mockSummaryQuery = { depositId: null };
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDocuments = [
		{
			document_id: 'test_doc_12345',
			date: '2022-03-15 12:00:00',
			type: 'test_document',
			period_from: '2022-03-01 00:00:00',
			period_to: '2022-03-31 23:59:59',
		},
		{
			document_id: 'test_doc_54321',
			date: '2022-02-15 12:00:00',
			type: 'test_document',
			period_from: '2022-02-01 00:00:00',
			period_to: '2022-02-28 23:59:59',
		},
	];
	const mockSummary = {
		count: 2,
	};
	const mockError = {
		error: 'Something went wrong!',
		code: 400,
	};

	// Sections in initial state are empty.
	const emptyState = {
		documents: {
			summary: {},
		},
	};
	const emptySummaryErrorState = {
		documents: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					error: {},
				},
			},
		},
	};

	// State is populated.
	const filledSuccessState = {
		documents: {
			[ getResourceId( mockQuery ) ]: {
				data: mockDocuments,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					data: mockSummary,
				},
			},
		},
	};
	const filledErrorState = {
		documents: {
			[ getResourceId( mockQuery ) ]: {
				error: mockError,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					error: mockError,
				},
			},
		},
	};

	test( 'Returns empty documents list when documents list is empty', () => {
		expect( getDocuments( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns documents list from state', () => {
		const expected = mockDocuments;
		expect( getDocuments( filledSuccessState, mockQuery ) ).toBe(
			expected
		);
	} );

	test( 'Returns empty documents list error when error is empty', () => {
		expect( getDocumentsError( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'Returns documents list error from state', () => {
		const expected = mockError;
		expect( getDocumentsError( filledErrorState, mockQuery ) ).toBe(
			expected
		);
	} );

	test( 'Returns empty documents summary when documents summary is empty', () => {
		expect(
			getDocumentsSummary( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns documents summary from state', () => {
		const expected = mockSummary;
		expect(
			getDocumentsSummary( filledSuccessState, mockSummaryQuery )
		).toBe( expected );
	} );

	test( 'Returns empty documents summary error when state is uninitialized', () => {
		expect(
			getDocumentsSummaryError( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns empty documents summary error when error is empty', () => {
		expect(
			getDocumentsSummaryError( emptySummaryErrorState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns documents summary error from state', () => {
		const expected = mockError;
		expect(
			getDocumentsSummaryError( filledErrorState, mockSummaryQuery )
		).toBe( expected );
	} );
} );
