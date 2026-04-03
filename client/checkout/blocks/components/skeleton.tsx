/**
 * External dependencies
 */
import React, { useContext } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 *
 * This component is ported from WooCommerce core's Skeleton component:
 * - Component: woocommerce/client/blocks/assets/js/base/components/skeleton/index.tsx
 * - Styles: woocommerce/client/blocks/assets/js/base/components/skeleton/style.scss
 * - Mixin: woocommerce/client/blocks/assets/css/abstracts/_mixins.scss (skeleton-animation, lines 352-383)
 * - Variables: woocommerce/client/blocks/assets/css/abstracts/_variables.scss ($universal-border-radius: 4px)
 * - Keyframes renamed from wc-skeleton-shimmer to wcpay-skeleton-shimmer to avoid collisions with core.
 */
import './skeleton.scss';
import SkeletonContext from './skeleton-context';

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

const LocalSkeleton = ( {
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
				'wcpay-skeleton__element',
				{
					'wcpay-skeleton__element--static': isStatic,
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

export const Skeleton = ( props: SkeletonProps ): JSX.Element => {
	const CoreSkeleton = useContext( SkeletonContext );
	return CoreSkeleton ? (
		<CoreSkeleton { ...props } />
	) : (
		<LocalSkeleton { ...props } />
	);
};
