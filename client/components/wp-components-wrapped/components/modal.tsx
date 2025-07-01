/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Modal as BundledModal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Modal = makeWrappedComponent( BundledModal, 'Modal' );
