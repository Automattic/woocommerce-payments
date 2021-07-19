/**
 * External dependencies
 */
import React, { useEffect, useRef, useState, memo } from 'react';
import { createPortal } from 'react-dom';
import classNames from 'classnames';
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */
import './styles.scss';

const rootElement = document.getElementById( 'wpbody-content' );

const isEventTriggeredWithin = ( event, element ) =>
	element && ( element === event.target || element.contains( event.target ) );

const useHideDelay = (
	isVisibleProp,
	{ hideDelay = 1000, triggerRef, tooltipRef }
) => {
	const [ isVisible, setIsVisible ] = useState( isVisibleProp );

	useEffect( () => {
		let timer = null;

		// element is marked as visible, no need to close it
		if ( isVisibleProp ) {
			rootElement.dispatchEvent( new Event( 'wcpay-tooltip-open' ) );
			setIsVisible( true );
			return;
		}

		// element is marked as not visible, hide it after `hideDelay` milliseconds
		timer = setTimeout( () => {
			setIsVisible( false );
		}, hideDelay );

		return () => {
			clearTimeout( timer );
		};
	}, [ setIsVisible, hideDelay, isVisibleProp ] );

	useEffect( () => {
		// immediately close this tooltip if another one opens
		const handleHideElement = () => {
			setIsVisible( false );
		};

		// do not close the tooltip if a click event has occurred and the click happened within the tooltip or within the wrapped element
		const handleDocumentClick = ( event ) => {
			if (
				isEventTriggeredWithin(
					event,
					triggerRef.current.firstChild
				) ||
				isEventTriggeredWithin( event, tooltipRef.current )
			) {
				return;
			}

			setIsVisible( false );
		};

		document.addEventListener( 'click', handleDocumentClick );
		rootElement.addEventListener( 'wcpay-tooltip-open', handleHideElement );

		return () => {
			document.removeEventListener( 'click', handleDocumentClick );
			rootElement.removeEventListener(
				'wcpay-tooltip-open',
				handleHideElement
			);
		};
	}, [ triggerRef, tooltipRef ] );

	return isVisible;
};

const TooltipPortal = memo( ( { children } ) => {
	const node = useRef( null );
	if ( ! node.current ) {
		node.current = document.createElement( 'div' );
		rootElement.appendChild( node.current );
	}

	// on component unmount, clear any reference to the created node
	useEffect( () => {
		return () => {
			rootElement.removeChild( node.current );
			node.current = null;
		};
	}, [] );

	return createPortal( children, node.current );
} );

const Tooltip = ( {
	className,
	children,
	content,
	closeDelay,
	isOpen,
	maxWidth = '250px',
} ) => {
	const wrapperRef = useRef( null );
	const tooltipWrapperRef = useRef( null );

	// using a delayed close, to allow the fade-out animation to complete
	const isTooltipVisible = useHideDelay( isOpen, {
		hideDelay: closeDelay,
		triggerRef: wrapperRef,
		tooltipRef: tooltipWrapperRef,
	} );

	useEffect( () => {
		const calculateTooltipPosition = () => {
			// calculate the position of the tooltip based on the wrapper's bounding rect
			if ( ! isTooltipVisible ) {
				return;
			}

			const tooltipElement = tooltipWrapperRef.current;

			tooltipElement.style.maxWidth = maxWidth;

			const wrappedElement = wrapperRef.current.firstChild;
			const wrappedElementRect = wrappedElement.getBoundingClientRect();
			const tooltipElementRect = tooltipElement.getBoundingClientRect();

			const tooltipHeight = tooltipElementRect.height;
			tooltipElement.style.top = `${
				wrappedElementRect.top - tooltipHeight - 8
			}px`;
			const elementMiddle =
				wrappedElement.offsetWidth / 2 + wrappedElementRect.left;
			const tooltipWidth = tooltipElement.offsetWidth;
			tooltipElement.style.left = `${
				elementMiddle - tooltipWidth / 2
			}px`;

			// make it visible only after all the calculations are done.
			tooltipElement.style.visibility = 'visible';
			tooltipElement.style.opacity = 1;
		};

		calculateTooltipPosition();

		const debouncedCalculation = debounce( calculateTooltipPosition, 150 );

		document.addEventListener( 'resize', debouncedCalculation );
		document.addEventListener( 'scroll', debouncedCalculation );

		return () => {
			document.removeEventListener( 'resize', debouncedCalculation );
			document.removeEventListener( 'scroll', debouncedCalculation );
		};
	}, [ isTooltipVisible, maxWidth ] );

	return (
		<>
			<div className="wcpay-tooltip__content-wrapper" ref={ wrapperRef }>
				{ children }
			</div>
			{ isTooltipVisible && (
				<TooltipPortal>
					<div
						ref={ tooltipWrapperRef }
						className={ classNames(
							'wcpay-tooltip__tooltip-wrapper',
							{ 'is-closing': ! isOpen }
						) }
					>
						<div
							className={ classNames(
								'wcpay-tooltip__tooltip',
								className
							) }
						>
							{ content }
						</div>
					</div>
				</TooltipPortal>
			) }
		</>
	);
};

export default Tooltip;
