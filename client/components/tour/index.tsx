/**
 * External dependencies
 */
import React from 'react';
import { createPortal } from 'react-dom';

/**
 * Internal dependencies
 */
import './style.scss';
import { TourProps } from './interfaces';
import WcPayTourContextProvider from './provider';
import {
	TourContent,
	TourFooter,
	TourImage,
	TourNextButton,
	TourPreviousButton,
	TourStep,
} from './components';

const Tour: React.FC< TourProps > = ( { children, ...props } ) => {
	if ( ! React.Children.count( children ) ) return null;

	return (
		<WcPayTourContextProvider { ...props }>
			{ createPortal(
				<>
					<div className="tour-modal__overlay"></div>
					{ children }
				</>,
				document.body
			) }
		</WcPayTourContextProvider>
	);
};

export default Object.assign( Tour, {
	Step: TourStep,
	Image: TourImage,
	Footer: TourFooter,
	Content: TourContent,
	NextButton: TourNextButton,
	PreviousButton: TourPreviousButton,
} );
