/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DocumentsList } from '../';
import { useDocuments, useDocumentsSummary } from 'data/index';
import type { Document } from 'data/documents/hooks';

jest.mock( 'data/index', () => ( {
	useDocuments: jest.fn(),
	useDocumentsSummary: jest.fn(),
} ) );

const mockUseDocuments = useDocuments as jest.MockedFunction<
	typeof useDocuments
>;

const mockUseDocumentsSummary = useDocumentsSummary as jest.MockedFunction<
	typeof useDocumentsSummary
>;

const getMockDocuments: () => Document[] = () => [
	{
		document_id: 'test_document_123456',
		date: '2020-01-02 17:46:02',
		type: 'test_document',
		period_from: '',
		period_to: '',
	},
	{
		document_id: 'test_document_654321',
		date: '2020-01-05 04:22:59',
		type: 'test_document',
		period_from: '',
		period_to: '',
	},
];

describe( 'Documents list', () => {
	let container: Element;
	let rerender: ( ui: React.ReactElement ) => void;
	beforeEach( () => {
		mockUseDocuments.mockReturnValue( {
			documents: getMockDocuments(),
			isLoading: false,
			documentsError: undefined,
		} );

		mockUseDocumentsSummary.mockReturnValue( {
			documentsSummary: {
				count: 10,
			},
			isLoading: false,
		} );

		( { container, rerender } = render( <DocumentsList /> ) );
	} );

	function expectSortingToBe( field: string, direction: string ) {
		expect( getQuery().orderby ).toEqual( field );
		expect( getQuery().order ).toEqual( direction );
		const useDocumentsCall =
			mockUseDocuments.mock.calls[
				mockUseDocuments.mock.calls.length - 1
			];
		expect( useDocumentsCall[ 0 ].orderby ).toEqual( field );
		expect( useDocumentsCall[ 0 ].order ).toEqual( direction );
	}

	function sortBy( field: string ) {
		user.click( screen.getByRole( 'button', { name: field } ) );
		rerender( <DocumentsList /> );
	}

	test( 'renders correctly', () => {
		expect( container ).toMatchSnapshot();
	} );

	test( 'sorts by default field date', () => {
		sortBy( 'Date and time' );
		expectSortingToBe( 'date', 'asc' );

		sortBy( 'Date and time' );
		expectSortingToBe( 'date', 'desc' );
	} );

	test( 'renders table summary only when the documents summary data is available', () => {
		mockUseDocumentsSummary.mockReturnValue( {
			documentsSummary: {},
			isLoading: true,
		} );

		( { container } = render( <DocumentsList /> ) );
		let tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);
		expect( tableSummary ).toHaveLength( 0 );

		mockUseDocumentsSummary.mockReturnValue( {
			documentsSummary: {
				count: 10,
			},
			isLoading: false,
		} );

		( { container } = render( <DocumentsList /> ) );
		tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 1 );
	} );

	test( 'renders table summary only when the documents summary data is available with a single document', () => {
		mockUseDocumentsSummary.mockReturnValue( {
			documentsSummary: {},
			isLoading: true,
		} );

		( { container } = render( <DocumentsList /> ) );
		let tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);
		expect( tableSummary ).toHaveLength( 0 );

		mockUseDocumentsSummary.mockReturnValue( {
			documentsSummary: {
				count: 1,
			},
			isLoading: false,
		} );

		( { container } = render( <DocumentsList /> ) );
		tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 1 );
		expect( container ).toMatchSnapshot();
	} );
} );
