/**
 * External dependencies
 */
// @ts-expect-error: Suppressing Module '"@wordpress/components"' has no exported member '__experimentalText'.
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis, no-restricted-syntax
import { __experimentalNumberControl as BundledExperimentalNumberControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const NumberControl = makeWrappedComponent(
	BundledExperimentalNumberControl,
	'__experimentalNumberControl'
);
