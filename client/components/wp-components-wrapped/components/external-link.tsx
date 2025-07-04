/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { ExternalLink as BundledExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const ExternalLink = makeWrappedComponent(
	BundledExternalLink,
	'ExternalLink'
);
