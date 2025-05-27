/**
 * External dependencies
 */
import React, { HTMLAttributes } from 'react';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './style.scss';

export const LoadBar: React.FC< React.PropsWithChildren<
	HTMLAttributes< HTMLDivElement >
> > = ( { className, ...rest } ) => {
	return (
		<div
			className={ clsx( 'wcpay-component-load-bar', className ) }
			{ ...rest }
		/>
	);
};

export default LoadBar;
