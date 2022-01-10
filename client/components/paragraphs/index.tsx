/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';

interface ParagraphsProps {
	children?: any[];
}

/**
 * Series of paragraphs rendered from an array of strings.
 *
 * @param {Array} Strings to render as separate paragraphs.
 *
 * @return	{Array} Paragraph elements.
 */
const Paragraphs: React.FC< ParagraphsProps > = ( {
	children = [],
} ): JSX.Element => {
	return (
		<>
			{ children.map( ( p, i ) => (
				<p key={ i }>{ p }</p>
			) ) }
		</>
	);
};

export default Paragraphs;
