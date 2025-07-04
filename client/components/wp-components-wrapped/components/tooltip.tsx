/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Tooltip as BundledTooltip } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Tooltip = makeWrappedComponent( BundledTooltip, 'Tooltip' );
