/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { FlexItem as BundledFlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const FlexItem = makeWrappedComponent( BundledFlexItem, 'FlexItem' );
