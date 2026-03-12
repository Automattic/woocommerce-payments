/**
 * External dependencies
 */
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from './skeleton';

interface CardSkeletonProps {
	isHidden?: boolean;
}

export const CardSkeleton = forwardRef< HTMLDivElement, CardSkeletonProps >(
	( { isHidden = false }, ref ): JSX.Element => {
		return (
			<div
				ref={ ref }
				className={ `wcpay-payment-element-skeleton ${
					isHidden ? 'is-hidden' : ''
				}` }
				aria-hidden={ isHidden }
			>
				<Skeleton height="3.5rem" borderRadius="4px" />
				<div className="wcpay-skeleton-row">
					<Skeleton height="3.5rem" borderRadius="4px" />
					<Skeleton height="3.5rem" borderRadius="4px" />
				</div>
			</div>
		);
	}
);

CardSkeleton.displayName = 'CardSkeleton';
