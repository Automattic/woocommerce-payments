/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FileUploadControl from '../file-upload-control';

// Mock the useViewport hook
jest.mock( 'wcpay/hooks/use-viewport', () => ( {
	useViewport: jest.fn(),
} ) );

import { useViewport } from 'wcpay/hooks/use-viewport';

// Mock FormFileUpload to directly render an input for testing

describe( 'FileUploadControl', () => {
	const mockUseViewport = useViewport as jest.MockedFunction<
		typeof useViewport
	>;

	const baseProps = {
		label: 'Order receipt',
		fileName: '',
		description: '',
		onFileChange: jest.fn(),
		onFileRemove: jest.fn(),
		disabled: false,
		isDone: false,
		accept: '.pdf',
	};

	beforeEach( () => {
		// Default mock for useViewport
		mockUseViewport.mockReturnValue( {
			viewportSize: { width: 1024, height: 768 },
			isVerySmallMobile: false,
			isMobile: false,
			isTablet: false,
			isDesktop: true,
		} );
	} );

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
		const fileInput = screen.getByTestId( 'form-file-upload-input' );
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

	it( 'renders file chip in header for larger screens', () => {
		mockUseViewport.mockReturnValue( {
			viewportSize: { width: 1024, height: 768 },
			isVerySmallMobile: false,
			isMobile: false,
			isTablet: false,
			isDesktop: true,
		} );

		render(
			<FileUploadControl
				{ ...baseProps }
				fileName="file.pdf"
				isDone={ true }
			/>
		);

		// File chip should be in the header (not after description)
		const infoHeader = screen
			.getByText( 'Order receipt' )
			.closest(
				'.wcpay-dispute-evidence-file-upload-control__info-header'
			);
		expect( infoHeader ).toHaveTextContent( 'file.pdf' );
	} );

	it( 'renders file chip after description for very small mobile screens', () => {
		mockUseViewport.mockReturnValue( {
			viewportSize: { width: 300, height: 600 },
			isVerySmallMobile: true,
			isMobile: true,
			isTablet: false,
			isDesktop: false,
		} );

		render(
			<FileUploadControl
				{ ...baseProps }
				fileName="file.pdf"
				description="A test description"
				isDone={ true }
			/>
		);

		// File chip should be after description, not in header
		const infoHeader = screen
			.getByText( 'Order receipt' )
			.closest(
				'.wcpay-dispute-evidence-file-upload-control__info-header'
			);
		expect( infoHeader ).not.toHaveTextContent( 'file.pdf' );

		// Description should be before the file chip
		const description = screen.getByText( 'A test description' );
		const fileChip = screen.getByText( 'file.pdf' );
		const position = description.compareDocumentPosition( fileChip );
		expect( position ).toBe( Node.DOCUMENT_POSITION_FOLLOWING );
	} );
} );
