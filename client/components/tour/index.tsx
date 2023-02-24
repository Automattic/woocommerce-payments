/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import classnames from 'classnames';
import { Button, Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

interface TourCoordinates {
	x: number;
	y: number;
}

interface TourOptionContentImage {
	src: string;
	mobileOnly?: boolean;
}

interface TourOptionContentButton {
	text: string;
}

interface TourOptionContent {
	title: string;
	description: string;
	image?: TourOptionContentImage;
	counter?: boolean;
	actionButton?: TourOptionContentButton;
	previousButton?: TourOptionContentButton;
}

interface TourOptionPosition {
	top?: number;
	left?: number;
	right?: number;
	bottom?: number;
}

interface TourOption {
	selector: string;
	position: TourOptionPosition;
	content: TourOptionContent;
}

interface TourProps {
	options: TourOption[];
	onTourEnd: () => void;
}

const Tour = ( { options, onTourEnd }: TourProps ): JSX.Element => {
	const containerRef = useRef< HTMLDivElement >( null );
	const [ coordinates, setCoordinates ] = useState< TourCoordinates | null >(
		null
	);
	const [ currentIndex, setCurrentIndex ] = useState( 0 );

	const { selector, position, content } = options[ currentIndex ] || {};
	const { title, description, image, actionButton, previousButton, counter } =
		content || {};

	const updateModalPosition = useCallback( () => {
		if ( ! selector ) return;

		const element = document.querySelector( selector );
		const container = containerRef.current;

		if ( ! element || ! container ) return;

		const elementRect = element.getBoundingClientRect();
		const containerRect = container.getBoundingClientRect();

		setCoordinates( () => {
			let x = window.scrollX + elementRect.left;
			let y = window.scrollY + elementRect.top - containerRect.height;

			if ( position ) {
				if ( position.bottom ) {
					y =
						elementRect.height -
						containerRect.height -
						position.bottom +
						32;
				}

				if ( position.left ) {
					x = elementRect.left + position.left;
				}
			}

			return {
				x: Math.floor( x ),
				y: Math.floor( y ),
			};
		} );
	}, [ selector, position ] );

	useEffect( () => {
		document.body.classList.add( 'modal-open' );

		// Disables automatic scroll restoration
		if ( history.scrollRestoration ) {
			history.scrollRestoration = 'manual';
		}

		return () => {
			document.body.classList.remove( 'modal-open' );
		};
	}, [] );

	useEffect( () => {
		const accountSettingsContainer = document.getElementById(
			'wcpay-account-settings-container'
		);

		if ( ! accountSettingsContainer ) return;

		const observer = new ResizeObserver( updateModalPosition );

		observer.observe( accountSettingsContainer );

		updateModalPosition();

		return () => {
			observer.unobserve( accountSettingsContainer );
		};
	}, [ updateModalPosition ] );

	useEffect( () => {
		if ( ! coordinates ) return;

		window.scrollTo( { left: coordinates.x, top: coordinates.y - 100 } );
	}, [ coordinates ] );

	const handleActionButtonClick = () => {
		if ( currentIndex >= options.length - 1 ) {
			onTourEnd();
			return;
		}

		setCurrentIndex( ( prev ) => prev + 1 );
	};

	const handlePreviousButtonClick = () => {
		setCurrentIndex( ( prev ) => prev - 1 );
	};

	return createPortal(
		<>
			<div className="tour-modal__overlay"></div>
			<div
				ref={ containerRef }
				className="tour-modal"
				style={
					coordinates
						? { top: coordinates.y, left: coordinates.x }
						: {}
				}
			>
				<Button
					onClick={ onTourEnd }
					className="tour-modal__close-button"
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
									{ previousButton.text || 'Previous' }
								</Button>
							) }

							{ actionButton && (
								<Button
									isPrimary
									onClick={ handleActionButtonClick }
								>
									{ actionButton.text || 'Next' }
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
