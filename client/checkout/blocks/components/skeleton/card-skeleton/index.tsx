/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from '../index';

interface CardSkeletonProps {
	isHidden?: boolean;
}

export const CardSkeleton = ( {
	isHidden = false,
}: CardSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
		>
			<Skeleton height="40px" borderRadius="4px" />
			<div className="wcpay-skeleton-row">
				<Skeleton height="40px" borderRadius="4px" />
				<Skeleton height="40px" borderRadius="4px" />
			</div>
		</div>
	);
};
