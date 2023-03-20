/**
 * External dependencies
 */
import React, { useState } from 'react';
import { noop } from 'lodash';

/**
 * Internal dependencies
 */
import TooltipBase, { TooltipBaseProps } from './tooltip-base';

type TooltipProps = TooltipBaseProps & {
	isVisible?: boolean;
	ignoreMouseHover?: boolean;
	onHide?: () => void;
};

const Tooltip: React.FC< TooltipProps > = ( {
	isVisible,
	ignoreMouseHover = false,
	onHide = noop,
	...props
} ) => {
	const [ isHovered, setIsHovered ] = useState( false );
	const [ isClicked, setIsClicked ] = useState( false );

	const handleMouseEnter = () => {
		// If ignoreMouseHover is true, we don't want to update state on mouse enter.
		if ( ! ignoreMouseHover ) {
			setIsHovered( true );
		}
	};
	const handleMouseLeave = () => {
		// If ignoreMouseHover is true, we don't want to update state on mouse leave.
		if ( ! ignoreMouseHover ) {
			setIsHovered( false );
			onHide();
		}
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
		<button
			className="wcpay-tooltip__content-wrapper"
			// on touch devices there's no mouse enter/leave, so we need to use a separate event (click/focus)
			// this creates 2 different (desirable) states on non-touch devices: if you hover and then click, the tooltip will persist
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
			onFocus={ handleMouseEnter }
			onBlur={ handleMouseLeave }
			onClick={ handleMouseClick }
			type={ 'button' }
		>
			<TooltipBase
				{ ...props }
				onHide={ handleHide }
				isVisible={ isVisible || isHovered || isClicked }
			/>
		</button>
	);
};

export default Tooltip;
