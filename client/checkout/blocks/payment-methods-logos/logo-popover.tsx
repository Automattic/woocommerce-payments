/**
 * External dependencies
 */
import React, {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
	useCallback,
} from 'react';
import { __ } from '@wordpress/i18n';

interface LogoPopoverProps {
	id: string;
	className?: string;
	children: React.ReactNode;
	anchor: HTMLElement | null;
	open: boolean;
	onClose?: () => void;
	dataTestId?: string;
}

export const LogoPopover: React.FC< React.PropsWithChildren<
	LogoPopoverProps
> > = ( { id, className, children, anchor, open, onClose, dataTestId } ) => {
	const popoverRef = useRef< HTMLDivElement >( null );
	const [ isPositioned, setIsPositioned ] = useState( false );

	const updatePosition = useCallback( () => {
		const popover = popoverRef.current;
		if ( ! popover || ! anchor ) {
			return;
		}

		const label = anchor.closest( 'label' );
		if ( ! label ) return;

		const labelRect = label.getBoundingClientRect();
		const labelStyle = window.getComputedStyle( label );
		const labelPaddingRight = parseInt( labelStyle.paddingRight, 10 );

		popover.style.position = 'fixed';
		popover.style.right = `${
			window.innerWidth - ( labelRect.right - labelPaddingRight )
		}px`;
		popover.style.top = `${ labelRect.top - 30 }px`;
		popover.style.left = 'auto';

		setIsPositioned( true );
	}, [ anchor ] );

	useLayoutEffect( () => {
		if ( open && anchor ) {
			// Use requestAnimationFrame to ensure the DOM has updated before positioning
			requestAnimationFrame( updatePosition );
		}
	}, [ open, anchor, updatePosition ] );

	useEffect( () => {
		if ( open && anchor ) {
			const observer = new MutationObserver( updatePosition );
			observer.observe( anchor, {
				attributes: true,
				childList: true,
				subtree: true,
			} );

			window.addEventListener( 'resize', updatePosition );
			window.addEventListener( 'scroll', updatePosition );

			const handleOutsideClick = ( event: MouseEvent ) => {
				if (
					popoverRef.current &&
					! popoverRef.current.contains( event.target as Node ) &&
					! anchor.contains( event.target as Node )
				) {
					onClose?.();
				}
			};

			const handleEscapeKey = ( event: KeyboardEvent ) => {
				if ( event.key === 'Escape' ) {
					onClose?.();
				}
			};

			document.addEventListener( 'mousedown', handleOutsideClick );
			document.addEventListener( 'keydown', handleEscapeKey );

			return () => {
				observer.disconnect();
				window.removeEventListener( 'resize', updatePosition );
				window.removeEventListener( 'scroll', updatePosition );
				document.removeEventListener( 'mousedown', handleOutsideClick );
				document.removeEventListener( 'keydown', handleEscapeKey );
			};
		}
	}, [ open, anchor, updatePosition, onClose ] );

	if ( ! open ) {
		return null;
	}

	return (
		<div
			id={ id }
			ref={ popoverRef }
			className={ `logo-popover ${ className || '' }` }
			style={ {
				position: 'fixed',
				zIndex: 1000,
				opacity: isPositioned ? 1 : 0,
				transition: 'opacity 0.2s',
				gridTemplateColumns: `repeat(${
					React.Children.count( children ) > 5
						? 5
						: React.Children.count( children )
				}, 38px)`,
				left: 'auto',
			} }
			role="dialog"
			aria-label={ __(
				'Supported Credit Card Brands',
				'woocommerce-payments'
			) }
			data-testid={ dataTestId }
		>
			{ children }
		</div>
	);
};
