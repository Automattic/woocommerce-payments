/**
 * External dependencies
 */
import React, {
	Fragment,
	useCallback,
	useEffect,
	useRef,
	useState,
} from 'react';
import { createPortal } from 'react-dom';
import classnames from 'classnames';
import { Button, Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import { TourCoordinates, TourProps } from './interfaces';
import { calculateCoordinates, getTourButtonData } from './utils';

const Tour = ( { options, onTourEnd }: TourProps ): JSX.Element => {
	const scrollRestoration = useRef< ScrollRestoration | null >( null );
	const containerRef = useRef< HTMLDivElement >( null );
	const [ coordinates, setCoordinates ] = useState< TourCoordinates | null >(
		null
	);
	const [ currentIndex, setCurrentIndex ] = useState( 0 );

	const { selector, position, content } = options[ currentIndex ] || {};
	const {
		title,
		image,
		counter,
		description,
		actionButton: customActionButton,
		previousButton: customPreviousButton,
	} = content || {};

	const actionButton = getTourButtonData(
		{ text: 'Next' },
		customActionButton
	);

	const previousButton = getTourButtonData(
		{ text: 'Previous' },
		customPreviousButton
	);

	const updateModalPosition = useCallback( () => {
		try {
			if ( ! selector )
				throw new Error(
					'No selector given for the current tour step.'
				);

			const element = document.querySelector( selector );
			const container = containerRef.current;

			if ( ! element || ! container )
				throw new Error(
					'No reference element or tour modal element found.'
				);

			const elementRect = element.getBoundingClientRect();
			const containerRect = container.getBoundingClientRect();

			setCoordinates(
				calculateCoordinates( elementRect, containerRect, position )
			);
		} catch ( e ) {
			setCoordinates( { x: 0, y: 0, sticky: true } );
		}
	}, [ selector, position ] );

	useEffect( () => {
		document.body.classList.add( 'modal-open' );
		document.documentElement.classList.add( 'smooth-scroll' );

		// Disables automatic scroll restoration
		if ( history.scrollRestoration ) {
			scrollRestoration.current = history.scrollRestoration;
			history.scrollRestoration = 'manual';
		}

		return () => {
			document.body.classList.remove( 'modal-open' );
			document.documentElement.classList.add( 'smooth-scroll' );

			if ( scrollRestoration.current ) {
				history.scrollRestoration = scrollRestoration.current;
			}
		};
	}, [] );

	useEffect( () => {
		let observer: ResizeObserver;

		if ( 'ResizeObserver' in window ) {
			observer = new ResizeObserver( updateModalPosition );
			observer.observe( document.body );
		}

		updateModalPosition();

		return () => {
			if ( observer ) {
				observer.unobserve( document.body );
			}
		};
	}, [ updateModalPosition ] );

	useEffect( () => {
		if ( ! coordinates ) return;

		const { x, y, scrollPadding } = coordinates;
		const [ paddingX, paddingY ] = scrollPadding || [ 0, 0 ];

		const left = x - paddingX;
		const top = y - paddingY - 50;

		window.scrollTo( { left, top } );
	}, [ coordinates ] );

	const handleActionButtonClick = () => {
		if ( currentIndex >= options.length - 1 ) {
			onTourEnd();
			return;
		}

		setCurrentIndex( ( prev ) => prev + 1 );
	};

	const handlePreviousButtonClick = () => {
		if ( currentIndex <= 0 ) {
			return;
		}

		setCurrentIndex( ( prev ) => prev - 1 );
	};

	if ( ! content ) {
		return <></>;
	}

	return createPortal(
		<>
			<div className="tour-modal__overlay"></div>
			<div
				ref={ containerRef }
				className={ classnames( 'tour-modal', {
					'tour-modal--arrow': coordinates?.arrow,
					[ `tour-modal--arrow-${ coordinates?.arrow }` ]: coordinates?.arrow,
					'tour-modal--sticky': coordinates?.sticky,
				} ) }
				style={
					coordinates
						? { top: coordinates.y, left: coordinates.x }
						: {}
				}
			>
				<Button
					onClick={ onTourEnd }
					className="tour-modal__close-button"
					aria-label="Close tour modal"
				>
					<Icon icon="no-alt" />
				</Button>

				{ image && (
					<img
						alt={ title }
						src={ image.src }
						width={ 350 }
						height={ 204 }
						className={ classnames( 'tour-modal__image', {
							'tour-modal__image--mobile': image.mobileOnly,
						} ) }
					/>
				) }

				<div className="tour-modal__content">
					<h3>{ title }</h3>
					<p>{ description }</p>

					<footer>
						{ counter && (
							<div className="tour-modal__counter">
								{ currentIndex + 1 } of { options.length }
							</div>
						) }

						<div className="tour-modal__buttons">
							{ previousButton && (
								<Button
									isSecondary
									onClick={ handlePreviousButtonClick }
								>
									{ previousButton.text }
								</Button>
							) }

							{ actionButton && (
								<Button
									isPrimary
									onClick={ handleActionButtonClick }
								>
									{ actionButton.text }
								</Button>
							) }
						</div>
					</footer>
				</div>
			</div>
		</>,
		document.body
	);
};

export default Tour;
