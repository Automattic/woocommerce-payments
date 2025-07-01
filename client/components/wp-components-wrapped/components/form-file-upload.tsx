/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { FormFileUpload as BundledFormFileUpload } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const FormFileUpload = makeWrappedComponent(
	BundledFormFileUpload,
	'FormFileUpload'
);
