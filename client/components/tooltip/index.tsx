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
	onHide?: () => void;
};

/**
 * Tooltip that shows on both hover and click.
 * To be used when the tooltip content is not interactive.
 *
 * @param {TooltipProps} props Component props.
 * @return {JSX.Element} Tooltip component.
 */
export const HoverTooltip: React.FC< TooltipProps > = ( {
	isVisible,
	onHide = noop,
	...props
} ) => {
	const [ isHovered, setIsHovered ] = useState( false );
	const [ isClicked, setIsClicked ] = useState( false );

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

/**
 * Tooltip that shows only on click events.
 * To be used when the tooltip content is interactive (e.g. links to documentation).
 *
 * @param {TooltipProps} props Component props.
 * @return {JSX.Element} Tooltip component.
 */
export const ClickTooltip: React.FC< TooltipProps > = ( {
	isVisible,
	onHide = noop,
	...props
} ) => {
	const [ isClicked, setIsClicked ] = useState( false );

	const handleMouseClick = () => {
		setIsClicked( ( val ) => ! val );
		if ( isClicked ) {
			onHide();
		}
	};
	const handleHide = () => {
		setIsClicked( false );
		onHide();
	};

	return (
		<button
			className="wcpay-tooltip__content-wrapper wcpay-tooltip--click__content-wrapper"
			onClick={ handleMouseClick }
			type={ 'button' }
		>
			<TooltipBase
				{ ...props }
				onHide={ handleHide }
				isVisible={ isVisible || isClicked }
			/>
		</button>
	);
};
