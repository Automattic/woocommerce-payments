/**
 * External dependencies
 */
import React, { ComponentProps } from 'react';
import {
	Card as WPCard,
	CardBody as WPCardBody,
	CardHeader as WPCardHeader,
} from '@wordpress/components';
import clsx from 'clsx';

// `@wordpress/components` doesn't treat its internal class names (e.g.
// `.components-card`) as a stable API, so our styles target these classes
// instead.

export const Card = ( {
	className,
	...props
}: ComponentProps< typeof WPCard > ) => (
	<WPCard className={ clsx( 'wcpay-card', className ) } { ...props } />
);

export const CardHeader = ( {
	className,
	...props
}: ComponentProps< typeof WPCardHeader > ) => (
	<WPCardHeader
		className={ clsx( 'wcpay-card__header', className ) }
		{ ...props }
	/>
);

export const CardBody = ( {
	className,
	...props
}: ComponentProps< typeof WPCardBody > ) => (
	<WPCardBody
		className={ clsx( 'wcpay-card__body', className ) }
		{ ...props }
	/>
);
