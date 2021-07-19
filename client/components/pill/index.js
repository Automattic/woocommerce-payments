/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

const Pill = ( { className, ...restProps } ) => (
	<span
		className={ classNames( 'wcpay-pill', className ) }
		{ ...restProps }
	/>
);

export default Pill;
