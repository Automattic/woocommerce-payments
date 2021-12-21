/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { FileUploadControl } from '../file-upload';
import type { DisputeFileUpload } from 'wcpay/types/disputes';

describe( 'FileUploadControl', () => {
	let props: DisputeFileUpload;
	const field = {
		key: 'field_key',
		label: 'Upload file',
		type: 'file',
	};
	const accept = '.pdf, image/png, image/jpeg';
	beforeEach( () => {
		props = {
			field,
			accept,
			isDone: false,
			isLoading: false,
			fileName: '',
			error: '',
			disabled: false,
			onFileChange: jest.fn(),
			onFileRemove: jest.fn(),
		};
	} );

	test( 'renders default file upload control', () => {
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		props.isLoading = true;
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload done state', () => {
		props.isDone = true;
		props.fileName = 'file.pdf';
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload failed state', () => {
		props.error = 'Error message';
		props.fileName = 'file.pdf';
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		expect( control ).toMatchSnapshot();
	} );

	test( 'triggers onFileChange', () => {
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		const fakeFile = {};
		const fakeEvent = { target: { files: [ fakeFile ] } };

		// Note: FormFileUpload does not associate file input with label so workaround is required to select it.
		const input = control.querySelector( 'input[type="file"]' );
		if ( input !== null ) {
			fireEvent.change( input, fakeEvent );
		}

		expect( props.onFileChange ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileChange ).toHaveBeenCalledWith(
			field.key,
			fakeFile
		);
	} );

	test( 'triggers onFileRemove', () => {
		props.fileName = 'file.pdf';
		props.isDone = true;
		const { getByRole } = render( <FileUploadControl { ...props } /> );
		fireEvent.click( getByRole( 'button', { name: /remove file/i } ) );
		expect( props.onFileRemove ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileRemove ).toHaveBeenCalledWith( field.key );
	} );

	test( 'renders disabled state', () => {
		props.disabled = true;
		props.isDone = true;
		props.fileName = 'file.pdf';
		const { container: control } = render(
			<FileUploadControl { ...props } />
		);
		expect( control ).toMatchSnapshot();
	} );
} );
