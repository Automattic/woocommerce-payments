/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { createEvent, fireEvent, render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DocumentsList } from '../';
import { useDocuments, useDocumentsSummary } from 'data/index';
import type { Document } from 'data/documents/hooks';
import { mocked } from 'ts-jest/utils';
import VatForm from 'wcpay/vat/form';

jest.mock( 'data/index', () => ( {
	useDocuments: jest.fn(),
	useDocumentsSummary: jest.fn(),
} ) );

jest.mock( 'wcpay/vat/form', () => jest.fn() );

const mockUseDocuments = useDocuments as jest.MockedFunction<
	typeof useDocuments
>;

const mockUseDocumentsSummary = useDocumentsSummary as jest.MockedFunction<
	typeof useDocumentsSummary
>;

declare const global: {
	wcpaySettings: {
		accountStatus: {
			hasSubmittedVatData: boolean;
		};
	};
};

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

describe( 'Document download button', () => {
	let downloadButton: HTMLElement;

	describe( 'for VAT invoices', () => {
		beforeEach( () => {
			mockUseDocuments.mockReturnValue( {
				documents: [
					{
						document_id: 'vat_invoice_123456',
						date: '2020-01-02 17:46:02',
						type: 'vat_invoice',
						period_from: '2020-01-01',
						period_to: '2020-01-31 23:59:59',
					},
				],
				isLoading: false,
				documentsError: undefined,
			} );

			mocked( VatForm ).mockImplementation( ( { onCompleted } ) => (
				<button
					onClick={ () =>
						onCompleted(
							'123456789',
							'Test company',
							'Test address'
						)
					}
				>
					Complete
				</button>
			) );
		} );

		describe( 'if VAT data has been submitted', () => {
			beforeEach( () => {
				global.wcpaySettings = {
					accountStatus: { hasSubmittedVatData: true },
				};

				render( <DocumentsList /> );

				downloadButton = screen.getByRole( 'link', {
					name: 'Download',
				} );
			} );

			it( 'should download the document ', () => {
				const clickEvent = createEvent.click( downloadButton );
				fireEvent( downloadButton, clickEvent );

				expect( clickEvent.defaultPrevented ).toBe( false );
			} );
		} );

		describe( "if VAT data hasn't been submitted", () => {
			beforeEach( () => {
				global.wcpaySettings = {
					accountStatus: { hasSubmittedVatData: false },
				};

				render( <DocumentsList /> );

				downloadButton = screen.getByRole( 'link', {
					name: 'Download',
				} );
			} );

			it( 'should not download the document', () => {
				const clickEvent = createEvent.click( downloadButton );
				fireEvent( downloadButton, clickEvent );

				expect( clickEvent.defaultPrevented ).toBe( true );
			} );

			it( 'should open the VAT form modal', () => {
				// Make sure the modal is not opened before clicking on the button.
				expect(
					screen.queryByRole( 'dialog', { name: 'VAT details' } )
				).toBeNull();

				user.click( downloadButton );

				expect(
					screen.getByRole( 'dialog', { name: 'VAT details' } )
				).toBeVisible();
			} );

			describe( 'after the VAT details are submitted', () => {
				const mockButtonOnClick = jest.fn();

				beforeEach( () => {
					user.click( downloadButton );

					// Add a mock button onclick handler to assert that it's been called a second time.
					downloadButton.addEventListener(
						'click',
						mockButtonOnClick
					);

					user.click( screen.getByText( 'Complete' ) );
				} );

				it( 'should close the modal', () => {
					expect(
						screen.queryByRole( 'dialog', { name: 'VAT details' } )
					).toBeNull();
				} );

				it( 'should set the hasSubmittedVatData flag to true', () => {
					expect(
						wcpaySettings.accountStatus.hasSubmittedVatData
					).toBe( true );
				} );

				it( 'should automatically click on the download button', () => {
					expect( mockButtonOnClick ).toHaveBeenCalled();
				} );
			} );
		} );
	} );
} );
