/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';

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
			className={ clsx( 'wcpay-payment-element-skeleton', {
				'is-hidden': isHidden,
			} ) }
			aria-hidden={ isHidden }
			onTransitionEnd={ onTransitionEnd }
		>
			<Skeleton height="6rem" borderRadius="4px" />
		</div>
	);
};
