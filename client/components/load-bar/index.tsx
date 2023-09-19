/**
 * External dependencies
 */
import React, { HTMLAttributes } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

export const LoadBar: React.FC< HTMLAttributes< HTMLDivElement > > = ( {
	className,
	...rest
} ) => {
	return (
		<div
			className={ classNames( 'wcpay-component-load-bar', className ) }
			{ ...rest }
		/>
	);
};

export default LoadBar;
