/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import type { Rating } from './types';

const strings: Record< Rating, string > = {
	'very-unhappy': '&#128542;',
	unhappy: '&#129764;',
	neutral: '&#128529;',
	happy: '&#128578;',
	'very-happy': '&#128525;',
};

interface Props {
	rating: Rating;
	setReviewRating: ( rating: Rating ) => void;
	disabled: boolean;
	currentRating?: Rating;
}
const Emoticon: React.FC< Props > = ( {
	rating,
	setReviewRating,
	disabled,
	currentRating,
} ) => {
	const buttonCss =
		'components-button has-icon' +
		( rating === currentRating ? ' selected' : '' );

	return (
		<button
			disabled={ disabled }
			type="button"
			className={ buttonCss }
			onClick={ function () {
				setReviewRating( rating );
			} }
		>
			<span
				role="img"
				aria-label={ rating }
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={ { __html: strings[ rating ] } }
			/>
		</button>
	);
};

export default Emoticon;
