/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import { Skeleton as LocalSkeleton } from './skeleton';

type SkeletonComponentType = typeof LocalSkeleton;

interface ApmSkeletonProps {
	isHidden?: boolean;
	onTransitionEnd?: () => void;
	skeletonComponent?: SkeletonComponentType;
}

export const ApmSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
	skeletonComponent: SkeletonEl = LocalSkeleton,
}: ApmSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ clsx( 'wcpay-payment-element-skeleton', {
				'is-hidden': isHidden,
			} ) }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			<SkeletonEl height="6rem" borderRadius="4px" />
		</div>
	);
};
