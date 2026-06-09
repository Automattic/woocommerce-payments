/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from './skeleton';

interface CardSkeletonProps {
	isHidden?: boolean;
	onTransitionEnd?: () => void;
	rowCount?: number;
}

const SingleRowSkeleton = () => (
	<div className="wcpay-skeleton-row">
		<Skeleton width="50%" height="3rem" borderRadius="4px" />
		<Skeleton width="25%" height="3rem" borderRadius="4px" />
		<Skeleton width="25%" height="3rem" borderRadius="4px" />
	</div>
);

const TwoRowSkeleton = () => (
	<>
		<Skeleton height="3.5rem" borderRadius="4px" />
		<div className="wcpay-skeleton-row">
			<Skeleton height="3.5rem" borderRadius="4px" />
			<Skeleton height="3.5rem" borderRadius="4px" />
		</div>
	</>
);

const ThreeRowSkeleton = () => (
	<>
		<Skeleton
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
		<Skeleton
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
		<Skeleton
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
	</>
);

const skeletonByRowCount: Record< number, React.FC > = {
	1: SingleRowSkeleton,
	2: TwoRowSkeleton,
	3: ThreeRowSkeleton,
};

export const CardSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
	rowCount = 2,
}: CardSkeletonProps ): JSX.Element => {
	const SkeletonLayout = skeletonByRowCount[ rowCount ] || TwoRowSkeleton;

	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			<SkeletonLayout />
		</div>
	);
};
