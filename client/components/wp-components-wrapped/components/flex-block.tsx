/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { FlexBlock as BundledFlexBlock } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const FlexBlock = makeWrappedComponent( BundledFlexBlock, 'FlexBlock' );
