/** @format */
/**
 * External dependencies
*/
import { shallow } from 'enzyme';
import { FormFileUpload, IconButton } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FileUploadControl } from '../file-upload';

describe( 'FileUploadControl', () => {
	let props;
	const field = {
		key: 'field_key',
		display: 'Upload file',
		control: 'file',
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
		const control = shallow( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		props.isLoading = true;
		const control = shallow( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload done state', () => {
		props.isDone = true;
		props.fileName = 'file.pdf';
		const control = shallow( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'renders upload failed state', () => {
		props.error = 'Error message';
		props.fileName = 'file.pdf';
		const control = shallow( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );

	test( 'triggers onFileChange', () => {
		props.onFileChange = jest.fn();
		const control = shallow( <FileUploadControl { ...props } /> );
		const fakeFile = {};
		const fakeEvent = { target: { files: [ fakeFile ] } };
		control.find( FormFileUpload ).simulate( 'change', fakeEvent );
		expect( props.onFileChange ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileChange ).toHaveBeenCalledWith( field.key, fakeFile );
	} );

	test( 'triggers onFileRemove', () => {
		props.fileName = 'file.pdf';
		props.isDone = true;
		props.onFileRemove = jest.fn();
		const control = shallow( <FileUploadControl { ...props } /> );
		control.find( IconButton ).simulate( 'click', {} );
		expect( props.onFileRemove ).toHaveBeenCalledTimes( 1 );
		expect( props.onFileRemove ).toHaveBeenCalledWith( field.key );
	} );

	test( 'renders disabled state', () => {
		props.disabled = true;
		props.isDone = true;
		props.fileName = 'file.pdf';
		const control = shallow( <FileUploadControl { ...props } /> );
		expect( control ).toMatchSnapshot();
	} );
} );
