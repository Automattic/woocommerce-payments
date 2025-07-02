/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Card as BundledCard } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Card = makeWrappedComponent( BundledCard, 'Card' );
