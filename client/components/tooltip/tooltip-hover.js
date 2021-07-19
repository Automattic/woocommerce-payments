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
	const [ isClicked, setIsClicked ] = useState( false );

	const handleMouseEnter = () => {
		setIsHovered( true );
	};
	const handleMouseLeave = () => {
		setIsHovered( false );
	};
	const handleMouseClick = () => {
		setIsClicked( ( val ) => ! val );
	};

	return (
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions,jsx-a11y/click-events-have-key-events
		<div
			className="wcpay-tooltip__content-wrapper"
			ref={ wrapperRef }
			// on touch devices there's no mouse enter/leave, so we need to use a separate event (click/focus)
			// this creates 2 different (desirable) states on non-touch devices: if you hover and then click, the tooltip will persist
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
			onFocus={ handleMouseEnter }
			onBlur={ handleMouseLeave }
			role="tooltip"
			onClick={ handleMouseClick }
		>
			<Tooltip { ...props } isOpen={ isOpen || isHovered || isClicked } />
		</div>
	);
};

export default TooltipHover;
