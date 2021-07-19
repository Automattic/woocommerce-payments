/**
 * External dependencies
 */
import React, { useRef, useState } from 'react';

/**
 * Internal dependencies
 */
import Tooltip from '.';

const TooltipHover = ( { isOpen, ...props } ) => {
	const wrapperRef = useRef( null );
	const [ isHovered, setIsHovered ] = useState( false );

	const handleMouseEnter = () => {
		setIsHovered( true );
	};
	const handleMouseLeave = () => {
		setIsHovered( false );
	};

	return (
		<div
			className="wcpay-tooltip__content-wrapper"
			ref={ wrapperRef }
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
		>
			<Tooltip { ...props } isOpen={ isOpen || isHovered } />
		</div>
	);
};

export default TooltipHover;
