/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
	Card,
	CardBody,
	CardHeader,
	CardMedia,
	CardFooter,
	Button,
	Flex,
	Icon,
} from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SpotlightProps } from './types';
import Chip from 'components/chip';
import './style.scss';

const showDelayMs = 4000; // 4 seconds

const Spotlight: React.FC< SpotlightProps > = ( {
	badge,
	heading,
	description,
	disclaimer,
	image,
	primaryButtonLabel,
	onPrimaryClick,
	secondaryButtonLabel,
	onSecondaryClick,
	onDismiss,
	showImmediately = false,
} ) => {
	const [ isVisible, setIsVisible ] = useState( false );
	const [ isAnimatingIn, setIsAnimatingIn ] = useState( false );
	const closeTimeoutRef = useRef< ReturnType< typeof setTimeout > | null >(
		null
	);
	const dialogRef = useRef< HTMLDivElement >( null );
	const previouslyFocusedElementRef = useRef< HTMLElement | null >( null );

	useEffect( () => {
		if ( showImmediately ) {
			setIsVisible( true );
			setIsAnimatingIn( true );
			return;
		}

		// Show the spotlight after a delay
		const timer = setTimeout( () => {
			setIsVisible( true );
			// Double RAF to ensure browser paints initial state before animating
			requestAnimationFrame( () => {
				requestAnimationFrame( () => {
					setIsAnimatingIn( true );
				} );
			} );
		}, showDelayMs );

		return () => clearTimeout( timer );
	}, [ showImmediately ] );

	// Cleanup close timeout on unmount
	useEffect( () => {
		return () => {
			if ( closeTimeoutRef.current ) {
				clearTimeout( closeTimeoutRef.current );
			}
		};
	}, [] );

	const handleClose = useCallback( () => {
		setIsAnimatingIn( false );
		// Wait for animation to complete before hiding
		closeTimeoutRef.current = setTimeout( () => {
			setIsVisible( false );
			onDismiss();
		}, 300 );
	}, [ onDismiss ] );

	// Focus management: save previous focus, focus dialog, handle Escape, trap focus, restore on close
	useEffect( () => {
		if ( ! isVisible || ! dialogRef.current ) {
			return;
		}

		const dialog = dialogRef.current;
		const ownerDocument = dialog.ownerDocument;

		// Save the currently focused element to restore later
		previouslyFocusedElementRef.current = ownerDocument.activeElement as HTMLElement;

		// Focus the dialog
		dialog.focus();

		const handleKeyDown = ( event: KeyboardEvent ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				handleClose();
				return;
			}

			// Focus trapping
			if ( event.key === 'Tab' ) {
				const focusableElements = dialog.querySelectorAll<
					HTMLElement
				>(
					'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
				);
				const firstElement = focusableElements[ 0 ];
				const lastElement =
					focusableElements[ focusableElements.length - 1 ];
				const activeElement = ownerDocument.activeElement;

				if ( event.shiftKey && activeElement === firstElement ) {
					// Shift + Tab: if on first element, wrap to last
					event.preventDefault();
					lastElement?.focus();
				} else if (
					! event.shiftKey &&
					activeElement === lastElement
				) {
					// Tab: if on last element, wrap to first
					event.preventDefault();
					firstElement?.focus();
				}
			}
		};

		ownerDocument.addEventListener( 'keydown', handleKeyDown );

		return () => {
			ownerDocument.removeEventListener( 'keydown', handleKeyDown );
			// Restore focus to previously focused element
			previouslyFocusedElementRef.current?.focus();
		};
	}, [ isVisible, handleClose ] );

	const handlePrimaryClick = () => {
		onPrimaryClick();
		handleClose();
	};

	if ( ! isVisible ) {
		return null;
	}

	return (
		<div
			className={ `wcpay-spotlight ${
				isAnimatingIn ? 'wcpay-spotlight--visible' : ''
			}` }
		>
			<div
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="spotlight-heading"
				tabIndex={ -1 }
				className="wcpay-spotlight__container"
			>
				<Card
					className={ `wcpay-spotlight__card ${
						image ? 'has-image' : ''
					}` }
					elevation={ 2 }
				>
					{ image && (
						<CardMedia className="wcpay-spotlight__image">
							{ typeof image === 'string' ? (
								<img
									src={ image }
									alt={ __(
										'Spotlight image',
										'woocommerce-payments'
									) }
								/>
							) : (
								image
							) }
						</CardMedia>
					) }

					<CardHeader
						isBorderless={ true }
						size="small"
						className="wcpay-spotlight__header"
					>
						<Flex
							className="wcpay-spotlight__controls"
							justify="flex-end"
						>
							<Button
								className="wcpay-spotlight__close-btn"
								label={ __( 'Close', 'woocommerce-payments' ) }
								icon={
									<Icon
										icon={ closeSmall }
										viewBox="6 4 12 14"
									/>
								}
								iconSize={ 24 }
								onClick={ handleClose }
							/>
						</Flex>
					</CardHeader>

					<CardBody className="wcpay-spotlight__body" size="small">
						{ badge && (
							<div className="wcpay-spotlight__badge">
								<Chip message={ badge } type="primary" />
							</div>
						) }
						<h2
							id="spotlight-heading"
							className="wcpay-spotlight__heading"
						>
							{ heading }
						</h2>
						<div className="wcpay-spotlight__description">
							{ description }
						</div>
						{ disclaimer && (
							<div className="wcpay-spotlight__disclaimer">
								{ disclaimer }
							</div>
						) }
					</CardBody>

					<CardFooter
						isBorderless={ true }
						size="small"
						className="wcpay-spotlight__footer"
					>
						<Flex justify="flex-end" gap={ 3 }>
							{ secondaryButtonLabel && (
								<Button
									className="wcpay-spotlight__secondary-btn"
									variant="tertiary"
									onClick={ onSecondaryClick }
								>
									{ secondaryButtonLabel }
								</Button>
							) }
							<Button
								className="wcpay-spotlight__primary-btn"
								variant="primary"
								onClick={ handlePrimaryClick }
							>
								{ primaryButtonLabel }
							</Button>
						</Flex>
					</CardFooter>
				</Card>
			</div>
		</div>
	);
};

export default Spotlight;
