/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { Skeleton } from '../index';

interface ApmSkeletonProps {
	isHidden?: boolean;
}

export const ApmSkeleton = ( {
	isHidden = false,
}: ApmSkeletonProps ): JSX.Element => {
	return (
		<div
			className={ `wcpay-payment-element-skeleton ${
				isHidden ? 'is-hidden' : ''
			}` }
			aria-hidden={ isHidden }
		>
			<Skeleton height="100px" borderRadius="4px" />
		</div>
	);
};
