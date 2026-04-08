/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { useUserPreferences } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import { DocumentsList } from '../';
import { useDocuments, useDocumentsSummary } from 'data/index';
import type { Document } from 'data/documents/hooks';
import VatForm from 'wcpay/vat/form';

jest.mock( 'data/index', () => ( {
	useDocuments: jest.fn(),
	useDocumentsSummary: jest.fn(),
} ) );

jest.mock( 'wcpay/vat/form', () => jest.fn() );

jest.mock( '@woocommerce/data', () => {
	const actualModule = jest.requireActual( '@woocommerce/data' );

	return {
		...actualModule,
		useUserPreferences: jest.fn(),
	};
} );

const mockUseDocuments = useDocuments as jest.MockedFunction<
	typeof useDocuments
>;

const mockUseDocumentsSummary = useDocumentsSummary as jest.MockedFunction<
	typeof useDocumentsSummary
>;

const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;

declare const global: {
	wcpaySettings: {
		accountStatus: {
			hasSubmittedVatData: boolean;
		};
		dateFormat: string;
	};
};

const getMockDocuments: () => Document[] = () => [
	{
		document_id: 'vat_invoice_123456',
		date: '2020-01-02 17:46:02',
		type: 'vat_invoice',
		period_from: '',
		period_to: '',
	},
	{
		document_id: 'vat_invoice_654321',
		date: '2020-01-05 04:22:59',
		type: 'vat_invoice',
		period_from: '',
		period_to: '',
	},
];

describe( 'Documents list', () => {
	let container: Element;
	let rerender: ( ui: React.ReactElement ) => void;
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: { hasSubmittedVatData: true },
			dateFormat: 'M j, Y',
		};

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

		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: jest.fn(),
			wc_payments_documents_hidden_columns: '',
			isRequesting: false,
		} as any );

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

	async function sortBy( field: string ) {
		await user.click( screen.getByRole( 'button', { name: field } ) );
		rerender( <DocumentsList /> );
	}

	test( 'renders correctly', () => {
		expect( container ).toMatchSnapshot();
	} );

	test( 'sorts by default field date', async () => {
		await sortBy( 'Date and time' );
		expectSortingToBe( 'date', 'asc' );

		await sortBy( 'Date and time' );
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
			window.open = jest.fn();

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

			jest.mocked( VatForm ).mockImplementation( ( { onCompleted } ) => (
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
					dateFormat: 'M j, Y',
				};

				render( <DocumentsList /> );

				downloadButton = screen.getByRole( 'button', {
					name: 'Download',
				} );
			} );

			it( 'should download the document ', async () => {
				await user.click( downloadButton );

				expect( window.open ).toHaveBeenCalledWith(
					'https://site.com/wp-json/wc/v3/payments/documents/vat_invoice_123456?_wpnonce=random_wp_rest_nonce',
					'_blank'
				);
			} );
		} );

		describe( "if VAT data hasn't been submitted", () => {
			beforeEach( () => {
				global.wcpaySettings = {
					accountStatus: { hasSubmittedVatData: false },
					dateFormat: 'M j, Y',
				};

				render( <DocumentsList /> );

				downloadButton = screen.getByRole( 'button', {
					name: 'Download',
				} );
			} );

			it( 'should not download the document', async () => {
				await user.click( downloadButton );

				expect( window.open ).not.toHaveBeenCalled();
			} );

			it( 'should open the tax details modal', async () => {
				// Make sure the modal is not opened before clicking on the button.
				expect(
					screen.queryByRole( 'dialog', {
						name: 'Set your tax details',
					} )
				).toBeNull();

				await user.click( downloadButton );

				expect(
					screen.getByRole( 'dialog', {
						name: 'Set your tax details',
					} )
				).toBeVisible();
			} );

			describe( 'after the tax details are submitted', () => {
				beforeEach( async () => {
					await user.click( downloadButton );

					await user.click( screen.getByText( 'Complete' ) );
				} );

				it( 'should close the modal', () => {
					expect(
						screen.queryByRole( 'dialog', {
							name: 'Set your tax details',
						} )
					).toBeNull();
				} );

				it( 'should set the hasSubmittedVatData flag to true', () => {
					expect(
						wcpaySettings.accountStatus.hasSubmittedVatData
					).toBe( true );
				} );

				it( 'should automatically download the document', () => {
					expect( window.open ).toHaveBeenCalledWith(
						'https://site.com/wp-json/wc/v3/payments/documents/vat_invoice_123456?_wpnonce=random_wp_rest_nonce',
						'_blank'
					);
				} );
			} );
		} );
	} );
} );

describe( 'Direct document download', () => {
	beforeEach( () => {
		window.open = jest.fn();

		global.wcpaySettings = {
			accountStatus: { hasSubmittedVatData: true },
			dateFormat: 'M j, Y',
		};
	} );

	it( 'should not download the document if document type is missing', () => {
		updateQueryString( { document_id: 'vat_invoice_123456' }, '/', {} );

		render( <DocumentsList /> );

		expect( window.open ).not.toHaveBeenCalled();
	} );

	it( 'should not download the document if document ID is missing', () => {
		updateQueryString( { document_type: 'vat_invoice' }, '/', {} );

		render( <DocumentsList /> );

		expect( window.open ).not.toHaveBeenCalled();
	} );

	it( 'should download the document in the same tab if document type and ID are in the query', () => {
		updateQueryString(
			{ document_id: 'vat_invoice_123456', document_type: 'vat_invoice' },
			'/',
			{}
		);

		render( <DocumentsList /> );

		expect( window.open ).toHaveBeenCalledWith(
			'https://site.com/wp-json/wc/v3/payments/documents/vat_invoice_123456?_wpnonce=random_wp_rest_nonce',
			'_self'
		);
	} );
} );
