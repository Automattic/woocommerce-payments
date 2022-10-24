/**
 * External dependencies
 */
import React, { useState } from 'react';
import { noop } from 'lodash';

/**
 * Internal dependencies
 */
import TooltipBase from './tooltip-base';

const Tooltip = ( { isVisible = false, onHide = noop, ...props } ) => {
	const [ isHovered, setIsHovered ] = useState( false );
	const [ isClicked, setIsClicked ] = useState( false );

	if ( ! props.content ) {
		return <>{ props.children }</>;
	}

	const handleMouseEnter = () => {
		setIsHovered( true );
	};
	const handleMouseLeave = () => {
		setIsHovered( false );
		onHide();
	};
	const handleMouseClick = () => {
		setIsClicked( ( val ) => ! val );
		if ( isClicked ) {
			onHide();
		}
	};
	const handleHide = () => {
		setIsHovered( false );
		setIsClicked( false );
		onHide();
	};

	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions
		<div
			// on touch devices there's no mouse enter/leave, so we need to use a separate event (click/focus)
			// this creates 2 different (desirable) states on non-touch devices: if you hover and then click, the tooltip will persist
			className="wcpay-tooltip__content-wrapper"
			onBlur={ handleMouseLeave }
			onClick={ handleMouseClick }
			onFocus={ handleMouseEnter }
			onKeyDown={ noop }
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
			onPointerEnter={ handleMouseEnter }
			onPointerLeave={ handleMouseLeave }
			tabIndex={ 0 }
		>
			<TooltipBase
				{ ...props }
				onHide={ handleHide }
				isVisible={ isVisible || isHovered || isClicked }
			/>
		</div>
	);
};

export default Tooltip;
