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

export default Tour;
