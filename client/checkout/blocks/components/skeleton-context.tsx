/**
 * External dependencies
 */
import React, { createContext } from 'react';

/**
 * Internal dependencies
 */
import type { SkeletonProps } from './skeleton';

/**
 * Context to allow injecting WooCommerce core's Skeleton component.
 * When core provides a Skeleton via PaymentMethodInterface `components` prop,
 * it can be set here so all child components use it automatically.
 */
const SkeletonContext = createContext< React.ComponentType<
	SkeletonProps
> | null >( null );

export default SkeletonContext;
