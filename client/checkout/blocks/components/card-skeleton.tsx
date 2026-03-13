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
}

export const CardSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
}: CardSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			<Skeleton height="3.5rem" borderRadius="4px" />
			<div className="wcpay-skeleton-row">
				<Skeleton height="3.5rem" borderRadius="4px" />
				<Skeleton height="3.5rem" borderRadius="4px" />
			</div>
		</div>
	);
};
