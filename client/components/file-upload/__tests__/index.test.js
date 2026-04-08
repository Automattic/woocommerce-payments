/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { FileUploadControl } from 'components/file-upload';

describe( 'FileUploadControl', () => {
	const accept = '.pdf, image/png, image/jpeg';
	let baseProps;

	beforeEach( () => {
		baseProps = {
			fieldKey: 'field_key',
			accept,
			isDone: false,
			isLoading: false,
			fileName: '',
			error: '',
			disabled: false,
			onFileChange: jest.fn(),
			onFileRemove: jest.fn(),
		};

		global.wcpaySettings = {
			restUrl: 'http://example.com/wp-json/',
		};
	} );

	test( 'renders default file upload control', () => {
		const { container: control } = render(
			<FileUploadControl { ...baseProps } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const { container: control } = render(
			<FileUploadControl { ...baseProps } isLoading={ true } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload done state', () => {
		const { container: control } = render(
			<FileUploadControl
				{ ...baseProps }
				isDone={ true }
				fileName="file.pdf"
			/>
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload failed state', () => {
		const { container: control } = render(
			<FileUploadControl
				{ ...baseProps }
				error="Error message"
				fileName="file.pdf"
			/>
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'triggers onFileChange', () => {
		const { container: control } = render(
			<FileUploadControl { ...baseProps } />
		);
		const fakeFile = {};
		const fakeEvent = { target: { files: [ fakeFile ] } };

		// Note: FormFileUpload does not associate file input with label so workaround is required to select it.
		const input = control.querySelector( 'input[type="file"]' );
		if ( input !== null ) {
			fireEvent.change( input, fakeEvent );
		}

		expect( baseProps.onFileChange ).toHaveBeenCalledTimes( 1 );
		expect( baseProps.onFileChange ).toHaveBeenCalledWith(
			'field_key',
			fakeFile
		);
	} );

	test( 'triggers onFileChange two times when selecting the same file again', () => {
		const { container: control } = render(
			<FileUploadControl { ...baseProps } />
		);

		const file = new File( [ 'hello' ], 'hello.png', {
			type: 'image/png',
		} );

		// Note: FormFileUpload does not associate file input with label so workaround is required to select it.
		const input = control.querySelector( 'input[type="file"]' );
		if ( input !== null ) {
			fireEvent.change( input, { target: { files: [ file ] } } );
			fireEvent.change( input, { target: { files: [ file ] } } );
		}

		expect( baseProps.onFileChange ).toHaveBeenNthCalledWith(
			2,
			'field_key',
			file
		);
	} );

	test( 'triggers onFileRemove', () => {
		const { getByRole } = render(
			<FileUploadControl
				{ ...baseProps }
				fileName="file.pdf"
				isDone={ true }
			/>
		);
		fireEvent.click( getByRole( 'button', { name: /remove file/i } ) );
		expect( baseProps.onFileRemove ).toHaveBeenCalledTimes( 1 );
		expect( baseProps.onFileRemove ).toHaveBeenCalledWith( 'field_key' );
	} );

	test( 'renders disabled state', () => {
		const { container: control } = render(
			<FileUploadControl
				{ ...baseProps }
				disabled={ true }
				isDone={ true }
				fileName="file.pdf"
			/>
		);
		expect( control ).toMatchSnapshot();
	} );
} );
