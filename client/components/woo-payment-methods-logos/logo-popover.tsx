import React, {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
	useCallback,
} from 'react';

interface LogoPopoverProps {
	id: string;
	className?: string;
	children: React.ReactNode;
	anchor: HTMLElement | null;
	open: boolean;
	onClose?: () => void;
	dataTestId?: string;
}

export const LogoPopover: React.FC< LogoPopoverProps > = ( {
	id,
	className,
	children,
	anchor,
	open,
	onClose,
	dataTestId,
} ) => {
	const popoverRef = useRef< HTMLDivElement >( null );
	const [ isPositioned, setIsPositioned ] = useState( false );

	const updatePosition = useCallback( () => {
		const popover = popoverRef.current;
		if ( ! popover || ! anchor ) {
			return;
		}

		// Get the most up-to-date anchor rect
		const anchorRect = anchor.getBoundingClientRect();

		// Temporarily make the popover visible to get correct dimensions
		popover.style.visibility = 'hidden';
		popover.style.display = 'block';
		const popoverRect = popover.getBoundingClientRect();
		popover.style.display = '';
		popover.style.visibility = '';

		const offset = 7;
		const left = anchorRect.left;
		// Position the popover above the anchor
		const top = anchorRect.top - popoverRect.height - offset;

		popover.style.position = 'fixed';
		popover.style.width = `${ anchorRect.width }px`;
		popover.style.left = `${ left }px`;
		popover.style.top = `${ top }px`;

		// Adjust position if popover goes off-screen
		if ( top < 0 ) {
			// If there's not enough space above, position it below the anchor
			popover.style.top = `${ anchorRect.bottom + offset }px`;
		}

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
			} }
			role="dialog"
			aria-label="Supported Credit Card Brands"
			data-testid={ dataTestId }
		>
			{ children }
		</div>
	);
};
