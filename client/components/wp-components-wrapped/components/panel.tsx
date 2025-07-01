/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Panel as BundledPanel } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Panel = makeWrappedComponent( BundledPanel, 'Panel' );
