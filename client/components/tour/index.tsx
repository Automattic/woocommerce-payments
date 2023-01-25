/**
 * External dependencies
 */
import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * Internal dependencies
 */
import './style.scss';

interface TourPosition {
	x: number;
	y: number;
}

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
const Tour = ( { options }: any ) => {
	const containerRef = useRef< HTMLDivElement >( null );
	const [ position, setPosition ] = useState< TourPosition | null >( null );
	const [ currentIndex, setCurrentIndex ] = useState( 0 );

	const { selector, content } = options[ currentIndex ] || {};
	const { title, description, image, actionButton, previousButton, counter } =
		content || {};

	useEffect( () => {
		if ( ! position ) return;

		window.scrollTo( { left: position.x, top: position.y - 100 } );
	}, [ position ] );

	useLayoutEffect( () => {
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

	const handleActionButtonClick = () => {
		setCurrentIndex( ( prev ) => prev + 1 );
	};

	const handlePreviousButtonClick = () => {
		setCurrentIndex( ( prev ) => prev - 1 );
	};

	if ( ! content ) return null;

	return createPortal(
		<div
			ref={ containerRef }
			className="tour-modal"
			style={ position ? { top: position.y, left: position.x } : {} }
		>
			{ image && (
				<img
					alt={ title }
					src={ typeof image === 'string' ? image : image.src }
					width={ 350 }
					height={ 204 }
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
		</div>,
		document.getElementsByTagName( 'body' )[ 0 ]
	);
};

export default Tour;
