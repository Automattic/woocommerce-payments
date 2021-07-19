/**
 * External dependencies
 */
import React, { forwardRef } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

const Pill = forwardRef( ( { className, ...restProps }, ref ) => (
	<span
		className={ classNames( 'wcpay-pill', className ) }
		{ ...restProps }
		ref={ ref }
	/>
) );

export default Pill;
