/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import IssuerEvidenceList from '../evidence-list';
import { useFiles } from 'wcpay/data/files';

const mockCreateNotice = jest.fn();

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( 'wcpay/data/files', () => ( {
	useFiles: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createNotice: mockCreateNotice } ) ),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;
const mockUseFiles = useFiles as jest.MockedFunction< typeof useFiles >;

describe( 'IssuerEvidenceList file download', () => {
	const fileName = 'evidence.pdf';

	const renderWithFileId = ( fileId: string ) => {
		mockUseFiles.mockReturnValue( {
			file: { id: fileId, filename: fileName },
			isLoading: false,
		} as ReturnType< typeof useFiles > );

		render(
			<IssuerEvidenceList
				issuerEvidence={ [
					{
						evidence_type: 'chargeback',
						file_evidence: [ fileId ],
						text_evidence: null,
					},
				] }
			/>
		);
		// The panel is collapsed by default.
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Issuer evidence' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: fileName } ) );
	};

	beforeEach( () => {
		jest.clearAllMocks();
		mockApiFetch.mockResolvedValue( { file_content: '' } );
		// jsdom cannot navigate to the generated download link.
		jest.spyOn( HTMLAnchorElement.prototype, 'click' ).mockImplementation(
			() => undefined
		);
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'encodes the file ID in the download path', async () => {
		const invalidFileId = 'file_1/../../x?y';

		renderWithFileId( invalidFileId );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/payments/file/file_1%2F..%2F..%2Fx%3Fy/content',
				method: 'GET',
			} );
		} );
	} );

	it( 'does not download a file with an invalid ID', async () => {
		renderWithFileId( '..' );

		await waitFor( () => {
			expect( mockCreateNotice ).toHaveBeenCalledWith(
				'error',
				'Error downloading file'
			);
		} );
		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );
} );
