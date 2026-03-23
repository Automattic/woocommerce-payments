/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { Skeleton as LocalSkeleton } from './skeleton';

type SkeletonComponentType = typeof LocalSkeleton;

interface CardSkeletonProps {
	isHidden?: boolean;
	onTransitionEnd?: () => void;
	rowCount?: number;
	skeletonComponent?: SkeletonComponentType;
}

const SingleRowSkeleton = ( {
	skeletonComponent: SkeletonEl,
}: {
	skeletonComponent: SkeletonComponentType;
} ) => (
	<div className="wcpay-skeleton-row">
		<SkeletonEl width="50%" height="3rem" borderRadius="4px" />
		<SkeletonEl width="25%" height="3rem" borderRadius="4px" />
		<SkeletonEl width="25%" height="3rem" borderRadius="4px" />
	</div>
);

const TwoRowSkeleton = ( {
	skeletonComponent: SkeletonEl,
}: {
	skeletonComponent: SkeletonComponentType;
} ) => (
	<>
		<SkeletonEl height="3.5rem" borderRadius="4px" />
		<div className="wcpay-skeleton-row">
			<SkeletonEl height="3.5rem" borderRadius="4px" />
			<SkeletonEl height="3.5rem" borderRadius="4px" />
		</div>
	</>
);

const ThreeRowSkeleton = ( {
	skeletonComponent: SkeletonEl,
}: {
	skeletonComponent: SkeletonComponentType;
} ) => (
	<>
		<SkeletonEl
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
		<SkeletonEl
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
		<SkeletonEl
			className="wcpay-skeleton-row"
			height="3.5rem"
			borderRadius="4px"
		/>
	</>
);

const skeletonByRowCount: Record<
	number,
	React.FC< { skeletonComponent: SkeletonComponentType } >
> = {
	1: SingleRowSkeleton,
	2: TwoRowSkeleton,
	3: ThreeRowSkeleton,
};

export const CardSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
	rowCount = 2,
	skeletonComponent: SkeletonEl = LocalSkeleton,
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
			<SkeletonLayout skeletonComponent={ SkeletonEl } />
		</div>
	);
};
