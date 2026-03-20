/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import { isVersionGreaterOrEqual } from 'utils/version';
import './skeleton-fallback.scss';

const hasCoreSkeleton = isVersionGreaterOrEqual(
	window.wcSettings?.wcVersion ?? '0',
	'10.2.0'
);

const skeletonClass = hasCoreSkeleton
	? 'wc-block-components-skeleton__element'
	: 'wcpay-skeleton__element';

export interface SkeletonProps {
	tag?: keyof JSX.IntrinsicElements;
	width?: string;
	height?: string;
	borderRadius?: string;
	className?: string;
	maxWidth?: string;
	isStatic?: boolean;
	ariaMessage?: string;
}

export const Skeleton = ( {
	tag: Tag = 'div',
	width = '100%',
	height = '8px',
	maxWidth = '',
	className = '',
	borderRadius = '',
	isStatic = false,
	ariaMessage,
}: SkeletonProps ): JSX.Element => {
	return (
		<Tag
			className={ clsx(
				skeletonClass,
				{
					[ `${ skeletonClass }--static` ]: isStatic,
				},
				className
			) }
			{ ...( ariaMessage
				? {
						'aria-live': 'polite' as const,
						'aria-label': ariaMessage,
				  }
				: {
						'aria-hidden': 'true' as const,
				  } ) }
			style={ {
				width,
				height,
				borderRadius,
				maxWidth,
			} }
		/>
	);
};
