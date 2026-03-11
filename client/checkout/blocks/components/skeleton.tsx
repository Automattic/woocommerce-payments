/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';

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
				'wc-block-components-skeleton__element',
				{
					'wc-block-components-skeleton__element--static': isStatic,
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
