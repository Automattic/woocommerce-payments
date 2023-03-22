/**
 * External dependencies
 */
import React, { ReactNode } from 'react';

interface TourContentProps {
	title: ReactNode;
	description: ReactNode;
}

const TourContent: React.FC< TourContentProps > = ( {
	title,
	description,
} ) => {
	return (
		<div className="tour-modal__content">
			<h3>{ title }</h3>
			<p>{ description }</p>
		</div>
	);
};

export default TourContent;
