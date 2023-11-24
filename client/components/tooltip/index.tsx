/**
 * External dependencies
 */
import React, { useState, useRef } from 'react';
import classNames from 'classnames';
import { noop } from 'lodash';
import { Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import TooltipBase, { TooltipBaseProps } from './tooltip-base';

type TooltipProps = TooltipBaseProps & {
	isVisible?: boolean;
	onHide?: () => void;
	/**
	 * An icon that will be used as the tooltip button. Replaces the component children.
	 */
	buttonIcon?: Icon.IconType< unknown >;
	/**
	 * A label for the tooltip button, visible to screen readers.
	 */
	buttonLabel?: string;
	/**
	 * The size of the tooltip button.
	 *
	 * @default 16
	 */
	buttonSize?: number;
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
	children,
	buttonIcon,
	buttonLabel,
	buttonSize = 16,
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
			>
				{ buttonIcon ? (
					<Icon
						icon={ buttonIcon }
						size={ buttonSize }
						aria-label={ buttonLabel }
					/>
				) : (
					children
				) }
			</TooltipBase>
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
	buttonIcon,
	buttonLabel,
	buttonSize = 16,
	children,
	className,
	...props
} ) => {
	const [ isClicked, setIsClicked ] = useState( false );

	// For interactive tooltips, we pass the tooltip button as the tooltip content's parent element.
	// This will allow the tooltip content to render with the correct tab index, aiding keyboard navigation.
	// Otherwise, the tooltip will be appended to the end of the document with an incorrect tab index.
	const tooltipParentRef = useRef< HTMLButtonElement | null >( null );

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
			ref={ tooltipParentRef }
		>
			<TooltipBase
				{ ...props }
				parentElement={ tooltipParentRef.current || undefined }
				onHide={ handleHide }
				isVisible={ isVisible || isClicked }
				className={ classNames(
					'wcpay-tooltip--click__tooltip',
					className
				) }
			>
				{ buttonIcon ? (
					<div
						tabIndex={ 0 }
						role="button"
						aria-label={ buttonLabel }
					>
						<Icon icon={ buttonIcon } size={ buttonSize } />
					</div>
				) : (
					children
				) }
			</TooltipBase>
		</button>
	);
};
