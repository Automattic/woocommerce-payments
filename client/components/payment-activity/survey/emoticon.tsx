/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

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
	onClick: ( event: React.MouseEvent< HTMLButtonElement > ) => void;
	disabled: boolean;
	isSelected: boolean;
}
const Emoticon: React.FC< Props > = ( {
	rating,
	onClick,
	disabled,
	isSelected,
} ) => {
	return (
		<button
			disabled={ disabled }
			type="button"
			onClick={ onClick }
			className={ classNames( 'components-button', 'has-icon', {
				selected: isSelected,
			} ) }
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
