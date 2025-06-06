/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FileUploadControl from '../file-upload-control';

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

describe( 'FileUploadControl', () => {
	const baseProps = {
		label: 'Order receipt',
		fileName: '',
		onFileChange: jest.fn(),
		onFileRemove: jest.fn(),
		disabled: false,
		isDone: false,
		accept: '.pdf',
	};

	it( 'renders label and upload button', () => {
		render( <FileUploadControl { ...baseProps } /> );
		expect( screen.getByText( 'Order receipt' ) ).toBeInTheDocument();
		const uploadInputs = screen.getAllByLabelText( /Upload file/i );
		expect( uploadInputs.length ).toBeGreaterThan( 0 );
	} );

	it( 'renders file name pill and remove button when uploaded', () => {
		render(
			<FileUploadControl
				{ ...baseProps }
				fileName="file.pdf"
				isDone={ true }
			/>
		);
		expect( screen.getByText( 'file.pdf' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( /Remove file/i ) ).toBeInTheDocument();
	} );

	it( 'calls onFileChange when file is selected', () => {
		render( <FileUploadControl { ...baseProps } /> );
		const fileInput = screen.getByTestId( 'mock-upload-input' );
		fireEvent.change( fileInput, {
			target: { files: [ new File( [ '' ], 'test.pdf' ) ] },
		} );
		expect( baseProps.onFileChange ).toHaveBeenCalled();
	} );

	it( 'calls onFileRemove when remove button is clicked', () => {
		render(
			<FileUploadControl
				{ ...baseProps }
				fileName="file.pdf"
				isDone={ true }
			/>
		);
		fireEvent.click( screen.getByLabelText( /Remove file/i ) );
		expect( baseProps.onFileRemove ).toHaveBeenCalled();
	} );
} );
