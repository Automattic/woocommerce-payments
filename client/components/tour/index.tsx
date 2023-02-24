/**
 * External dependencies
 */
import React, {
	ReactNode,
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from 'react';
import { createPortal } from 'react-dom';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

interface TourPosition {
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

interface TourOption {
	selector: string;
	content: TourOptionContent;
}

interface TourProps {
	options: TourOption[];
}

const Tour = ( { options }: TourProps ): ReactNode => {
	const containerRef = useRef< HTMLDivElement >( null );
	const [ position, setPosition ] = useState< TourPosition | null >( null );
	const [ currentIndex, setCurrentIndex ] = useState( 0 );

	const { selector, content } = options[ currentIndex ] || {};
	const { title, description, image, actionButton, previousButton, counter } =
		content || {};

	const updateModalPosition = useCallback( () => {
		if ( ! selector ) return;

		const container = containerRef.current;
		const element = document.querySelector( selector );

		if ( ! element || ! container ) return;

		const { top, left } = element.getBoundingClientRect();
		const rect2 = container.getBoundingClientRect();

		setPosition( {
			x: Math.floor( window.scrollX + left ),
			y: Math.floor( window.scrollY + top - rect2.height ),
		} );
	}, [ selector ] );

	useEffect( () => {
		const accountSettingsContainer = document.getElementById(
			'wcpay-account-settings-container'
		);

		if ( ! accountSettingsContainer ) return;

		const observer = new ResizeObserver( updateModalPosition );

		observer.observe( accountSettingsContainer );

		return () => {
			observer.unobserve( accountSettingsContainer );
		};
	}, [ updateModalPosition ] );

	useEffect( () => {
		document.body.classList.add( 'modal-open' );

		return () => {
			document.body.classList.remove( 'modal-open' );
		};
	}, [] );

	useLayoutEffect( () => {
		updateModalPosition();
	}, [ updateModalPosition ] );

	useLayoutEffect( () => {
		if ( ! position ) return;

		window.scrollTo( { left: position.x, top: position.y - 100 } );
	}, [ position ] );

	const handleActionButtonClick = () => {
		setCurrentIndex( ( prev ) => prev + 1 );
	};

	const handlePreviousButtonClick = () => {
		setCurrentIndex( ( prev ) => prev - 1 );
	};

	if ( ! content ) return null;

	return createPortal(
		<>
			<div className="tour-modal__overlay"></div>
			<div
				ref={ containerRef }
				className="tour-modal"
				style={ position ? { top: position.y, left: position.x } : {} }
			>
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

				<h3>{ title }</h3>
				<p>{ description }</p>

				<footer>
					{ counter && (
						<div>
							{ currentIndex + 1 } of { options.length }
						</div>
					) }

					<div>
						{ previousButton && (
							<button onClick={ handlePreviousButtonClick }>
								{ previousButton.text || 'Previous' }
							</button>
						) }

						{ actionButton && (
							<button onClick={ handleActionButtonClick }>
								{ actionButton.text || 'Next' }
							</button>
						) }
					</div>
				</footer>
			</div>
		</>,
		document.body
	);
};

export default Tour;
