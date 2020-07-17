/** @format */

/**
 * External dependencies
 */
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { FileUploadControl } from '../file-upload';

describe( 'FileUploadControl', () => {
	let props;
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
		};
	} );

	test( 'renders default file upload control', () => {
		const { container: control } = render( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		props.isLoading = true;
		const { container: control } = render( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload done state', () => {
		props.isDone = true;
		props.fileName = 'file.pdf';
		const { container: control } = render( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload failed state', () => {
		props.error = 'Error message';
		props.fileName = 'file.pdf';
		const { container: control } = render( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'triggers onFileChange', () => {
		props.onFileChange = jest.fn();
		const { container: control } = render( <FileUploadControl { ...props } /> );
		const fakeFile = {};
		const fakeEvent = { target: { files: [ fakeFile ] } };

		// Note: FormFileUpload does not associate file input with label so workaround is required to select it.
		const input = control.querySelector( 'input[type="file"]' );
		fireEvent.change( input, fakeEvent );

		expect( props.onFileChange ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileChange ).toHaveBeenCalledWith( field.key, fakeFile );
	} );

	test( 'triggers onFileRemove', () => {
		props.fileName = 'file.pdf';
		props.isDone = true;
		props.onFileRemove = jest.fn();
		const { getByRole } = render( <FileUploadControl { ...props } /> );
		fireEvent.click( getByRole( 'button', { name: /remove file/i } ) );
		expect( props.onFileRemove ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileRemove ).toHaveBeenCalledWith( field.key );
	} );

	test( 'renders disabled state', () => {
		props.disabled = true;
		props.isDone = true;
		props.fileName = 'file.pdf';
		const { container: control } = render( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );
} );
