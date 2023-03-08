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

/* eslint-disable @typescript-eslint/naming-convention */
interface TourComponents {
	Step: typeof TourStep;
	Image: typeof TourImage;
	Footer: typeof TourFooter;
	Content: typeof TourContent;
	NextButton: typeof TourNextButton;
	PreviousButton: typeof TourPreviousButton;
}
/* eslint-enable @typescript-eslint/naming-convention */

const Tour: React.FC< TourProps > & TourComponents = ( {
	children,
	...props
} ) => {
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

Tour.Step = TourStep;
Tour.Image = TourImage;
Tour.Footer = TourFooter;
Tour.Content = TourContent;
Tour.NextButton = TourNextButton;
Tour.PreviousButton = TourPreviousButton;

export default Tour;
