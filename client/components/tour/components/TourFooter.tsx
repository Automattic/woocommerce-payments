/**
 * External dependencies
 */
import React, { useContext } from 'react';

/**
 * Internal dependencies
 */
import WcPayTourContext from '../context';
import { TourPreviousButton, TourNextButton } from '.';

interface TourFooterProps {
	showCounter?: boolean;
}

const TourFooter: React.FC< TourFooterProps > = ( {
	children,
	showCounter = true,
} ) => {
	const { currentIndex, steps } = useContext( WcPayTourContext );

	const renderButtons = () => {
		if ( children ) return children;

		return (
			<>
				<TourPreviousButton />
				<TourNextButton />
			</>
		);
	};

	return (
		<footer className="tour-modal__footer">
			{ showCounter && (
				<div className="tour-modal__counter">
					{ currentIndex + 1 } of { steps.length }
				</div>
			) }

			<div className="tour-modal__buttons">{ renderButtons() }</div>
		</footer>
	);
};

export default TourFooter;
