/**
 * External dependencies
 */
import React from 'react';
import strings from './strings';

const Emoticons = ( props ) => {
	const { rating, setReviewRating, disabled, currentRating } = props;

	const buttonCss =
		'components-button has-icon' +
		( rating === currentRating ? ' selected' : '' );

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
				<span
					role="img"
					aria-label="emoticon"
					dangerouslySetInnerHTML={ {
						__html: strings[ `${ rating }` ],
					} }
				/>
			</button>
		</>
	);
};

export default Emoticons;
