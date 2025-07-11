/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RecommendedDocuments from '../recommended-documents';

// Mock FormFileUpload to directly render an input for testing
jest.mock( 'wcpay/components/wp-components-wrapped', () => {
	const original = jest.requireActual(
		'wcpay/components/wp-components-wrapped'
	);
	return {
		...original,
		FormFileUpload: ( { onChange, render: renderProp }: any ) => (
			<>
				<input
					aria-label="Upload file"
					type="file"
					onChange={ onChange }
					data-testid="mock-upload-input"
				/>
				{ /* eslint-disable-next-line @typescript-eslint/no-empty-function */ }
				{ renderProp( { openFileDialog: () => {} } ) }
			</>
		),
	};
} );

describe( 'RecommendedDocuments', () => {
	const fields = [
		{
			key: 'doc1',
			label: 'Order receipt',
			fileName: '',
			description:
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
			onFileChange: jest.fn(),
			onFileRemove: jest.fn(),
			uploaded: false,
			readOnly: false,
		},
		{
			key: 'doc2',
			label: 'Customer communication',
			fileName: 'file.pdf',
			description:
				'Any correspondence with the customer regarding this purchase.',
			onFileChange: jest.fn(),
			onFileRemove: jest.fn(),
			uploaded: true,
			readOnly: false,
		},
	];

	it( 'renders all document fields', () => {
		render( <RecommendedDocuments fields={ fields } /> );
		expect( screen.getByText( 'Order receipt' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Customer communication' )
		).toBeInTheDocument();
	} );

	it( 'disables controls when readOnly', () => {
		render( <RecommendedDocuments fields={ fields } readOnly={ true } /> );
		const buttons = screen.getAllByRole( 'button' );
		buttons.forEach( ( btn ) => expect( btn ).toBeDisabled() );
	} );

	it( 'calls upload/remove handlers', () => {
		render( <RecommendedDocuments fields={ fields } /> );
		// Simulate file upload for first field
		const fileInput = screen.getAllByTestId( 'mock-upload-input' )[ 0 ];
		fireEvent.change( fileInput, {
			target: { files: [ new File( [ '' ], 'test.pdf' ) ] },
		} );
		expect( fields[ 0 ].onFileChange ).toHaveBeenCalled();
		// Simulate remove for second field
		const removeButtons = screen.getAllByLabelText( /Remove file/i );
		fireEvent.click( removeButtons[ 0 ] );
		expect( fields[ 1 ].onFileRemove ).toHaveBeenCalled();
	} );

	describe( 'helper link functionality', () => {
		it( 'renders helper link with correct href and text', () => {
			render( <RecommendedDocuments fields={ fields } /> );
			const helperLink = screen.getByRole( 'link', {
				name: /Learn more about documents/i,
			} );
			expect( helperLink ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#challenge-or-accept'
			);

			expect(
				screen.getByText( 'Learn more about documents' )
			).toBeInTheDocument();
		} );

		it( 'renders helper link in the correct container', () => {
			render( <RecommendedDocuments fields={ fields } /> );
			const helperLink = screen.getByRole( 'link', {
				name: /Learn more about documents/i,
			} );
			const helperLinkContainer = helperLink.closest(
				'.wcpay-dispute-evidence-recommended-documents__helper-link'
			);
			expect( helperLinkContainer ).toBeInTheDocument();
		} );
	} );
} );
