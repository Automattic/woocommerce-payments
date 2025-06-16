/**
 * External dependencies
 */
import React, { useState, useEffect, useCallback, useRef } from 'react';
/**
 * Internal dependencies
 */
import { LogoPopover } from './logo-popover';
import './style.scss';

interface BreakpointConfig {
	breakpoint: number;
	maxElements: number;
}

interface PaymentMethodsLogosProps {
	maxElements: number;
	paymentMethods: { name: string; component: string }[];
	breakpointConfigs?: BreakpointConfig[];
}

const breakpointConfigsDefault = [
	{ breakpoint: 480, maxElements: 5 },
	{ breakpoint: 768, maxElements: 7 },
];
const paymentMethodsDefault: never[] = [];
export const PaymentMethodsLogos: React.FC< PaymentMethodsLogosProps > = ( {
	maxElements = 10,
	paymentMethods = paymentMethodsDefault,
	breakpointConfigs = breakpointConfigsDefault,
} ) => {
	const [ maxShownElements, setMaxShownElements ] = useState( maxElements );
	const [
		popoverAnchor,
		setPopoverAnchor,
	] = useState< HTMLDivElement | null >( null );
	const [ popoverOpen, setPopoverOpen ] = useState( false );
	const [ shouldHavePopover, setShouldHavePopover ] = useState( false );

	const togglePopover = () => setPopoverOpen( ! popoverOpen );

	const anchorRef = useCallback( ( node: HTMLDivElement | null ) => {
		if ( node !== null ) {
			setPopoverAnchor( node );
		}
	}, [] );

	const buttonRef = useRef< HTMLDivElement | null >( null );

	const handlePopoverClose = useCallback( () => {
		setPopoverOpen( false );
		buttonRef.current?.focus();
	}, [] );

	// Try to find the preview iframe in the page editor.
	const previewFrame = document.querySelector(
		'iframe[name="editor-canvas"]'
	);

	useEffect( () => {
		const updateMaxElements = () => {
			// Get the actual width based on whether we're in an iframe or not.
			let width: number;

			if ( previewFrame ) {
				// Get the width from the iframe's clientWidth.
				width = previewFrame.clientWidth;
			} else {
				width = window.innerWidth;
			}

			const sortedConfigs = [ ...breakpointConfigs ].sort(
				( a, b ) => a.breakpoint - b.breakpoint
			);
			const config = sortedConfigs.find(
				( cfg ) => width <= cfg.breakpoint
			);

			setMaxShownElements( config ? config.maxElements : maxElements );
		};

		// Initial update
		updateMaxElements();

		// Use ResizeObserver if available, otherwise fallback to window resize
		if ( typeof ResizeObserver !== 'undefined' ) {
			const resizeObserver = new ResizeObserver( updateMaxElements );

			if ( previewFrame ) {
				resizeObserver.observe( previewFrame );
			} else {
				// Fallback to window resize
				window.addEventListener( 'resize', updateMaxElements );
			}

			return () => {
				resizeObserver.disconnect();
				window.removeEventListener( 'resize', updateMaxElements );
			};
		}

		// Fallback for browsers without ResizeObserver
		window.addEventListener( 'resize', updateMaxElements );
		return () => window.removeEventListener( 'resize', updateMaxElements );
	}, [ breakpointConfigs, maxElements, previewFrame ] );

	useEffect( () => {
		if ( popoverAnchor ) {
			buttonRef.current = popoverAnchor;
		}
	}, [ popoverAnchor ] );

	useEffect( () => {
		setShouldHavePopover( paymentMethods.length > maxShownElements );
	}, [ maxShownElements, paymentMethods.length ] );

	return (
		<>
			<div className="payment-methods--logos">
				<div
					ref={ anchorRef }
					{ ...( shouldHavePopover && {
						onClick: togglePopover,
						onKeyDown: ( e ) => {
							if ( e.key === 'Enter' || e.key === ' ' ) {
								e.preventDefault();
								togglePopover();
							}
						},
						role: 'button',
						tabIndex: 0,
						'aria-expanded': popoverOpen,
						'aria-controls': 'payment-methods-popover',
					} ) }
					data-testid="payment-methods-logos"
				>
					{ paymentMethods
						.slice( 0, maxShownElements )
						.map( ( pm ) => (
							<img
								key={ pm.name }
								alt={ pm.name }
								src={ pm.component }
								width={ 38 }
								height={ 24 }
							/>
						) ) }
					{ shouldHavePopover && (
						<div className="payment-methods--logos-count">
							+ { paymentMethods.length - maxShownElements }
						</div>
					) }
				</div>
			</div>
			{ shouldHavePopover && popoverOpen && (
				<LogoPopover
					id="payment-methods-popover"
					className="payment-methods--logos-popover"
					anchor={ popoverAnchor }
					open={ popoverOpen }
					onClose={ handlePopoverClose }
					dataTestId="payment-methods-popover"
				>
					{ paymentMethods.slice( maxShownElements ).map( ( pm ) => (
						<img
							key={ pm.name }
							alt={ pm.name }
							src={ pm.component }
							width={ 38 }
							height={ 24 }
						/>
					) ) }
				</LogoPopover>
			) }
		</>
	);
};
