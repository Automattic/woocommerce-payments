/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { TabPanel as BundledTabPanel } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const TabPanel = makeWrappedComponent( BundledTabPanel, 'TabPanel' );
