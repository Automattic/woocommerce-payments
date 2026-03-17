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
	isSingleRow?: boolean;
}

export const CardSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
	isSingleRow = false,
}: CardSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			{ isSingleRow ? (
				<div className="wcpay-skeleton-row">
					<Skeleton width="50%" height="3.5rem" borderRadius="4px" />
					<Skeleton width="25%" height="3.5rem" borderRadius="4px" />
					<Skeleton width="25%" height="3.5rem" borderRadius="4px" />
				</div>
			) : (
				<>
					<Skeleton height="3.5rem" borderRadius="4px" />
					<div className="wcpay-skeleton-row">
						<Skeleton height="3.5rem" borderRadius="4px" />
						<Skeleton height="3.5rem" borderRadius="4px" />
					</div>
				</>
			) }
		</div>
	);
};
