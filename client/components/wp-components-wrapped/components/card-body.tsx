/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { CardBody as BundledCardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CardBody = makeWrappedComponent( BundledCardBody, 'CardBody' );
