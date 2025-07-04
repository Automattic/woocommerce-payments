/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { PanelBody as BundledPanelBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const PanelBody = makeWrappedComponent( BundledPanelBody, 'PanelBody' );
