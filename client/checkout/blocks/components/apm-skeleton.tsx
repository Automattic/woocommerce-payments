/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from './skeleton';

interface ApmSkeletonProps {
	isHidden?: boolean;
	onTransitionEnd?: () => void;
}

export const ApmSkeleton = ( {
	isHidden = false,
	onTransitionEnd,
}: ApmSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			<Skeleton height="6rem" borderRadius="4px" />
		</div>
	);
};
