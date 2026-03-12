/**
 * External dependencies
 */
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from './skeleton';

interface ApmSkeletonProps {
	isHidden?: boolean;
}

export const ApmSkeleton = forwardRef< HTMLDivElement, ApmSkeletonProps >(
	( { isHidden = false }, ref ): JSX.Element => {
		return (
			<div
				ref={ ref }
				className={ `wcpay-payment-element-skeleton ${
					isHidden ? 'is-hidden' : ''
				}` }
				aria-hidden={ isHidden }
			>
				<Skeleton height="6rem" borderRadius="4px" />
			</div>
		);
	}
);

ApmSkeleton.displayName = 'ApmSkeleton';
