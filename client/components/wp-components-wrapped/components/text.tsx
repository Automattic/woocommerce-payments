/**
 * External dependencies
 */
// @ts-expect-error: Suppressing Module '"@wordpress/components"' has no exported member '__experimentalText'.
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis, no-restricted-syntax
import { __experimentalText as BundledExperimentalText } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Text = makeWrappedComponent(
	BundledExperimentalText,
	'__experimentalText'
);
