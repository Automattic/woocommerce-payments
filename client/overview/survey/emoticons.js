/**
 * External dependencies
 */
import React from 'react';

const Emoticons = ( props ) => {
	const { rating, setReviewRating, disabled, currentRating } = props;

	const buttonCss =
		'components-button has-icon' +
		( rating === currentRating ? ' selected' : '' );

	const getIcon = function () {
		if ( rating === '1' ) {
			return (
				<span role="img" aria-label="emoticon">
					&#128542;
				</span>
			);
		}
		if ( rating === '2' ) {
			return (
				<span role="img" aria-label="emoticon">
					&#129764;
				</span>
			);
		}

		if ( rating === '3' ) {
			return (
				<span role="img" aria-label="emoticon">
					&#128529;
				</span>
			);
		}

		if ( rating === '4' ) {
			return (
				<span role="img" aria-label="emoticon">
					&#128578;
				</span>
			);
		}

		if ( rating === '5' ) {
			return (
				<span role="img" aria-label="emoticon">
					&#128525;
				</span>
			);
		}
	};

	return (
		<>
			<button
				disabled={ disabled }
				type="button"
				className={ buttonCss }
				onClick={ function () {
					setReviewRating( rating );
				} }
			>
				{ getIcon() }
			</button>
		</>
	);
};

export default Emoticons;
