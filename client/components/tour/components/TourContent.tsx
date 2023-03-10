/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

interface TourContentProps {
	title: string;
	description: string;
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
