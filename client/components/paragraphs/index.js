/** @format **/

/**
 * Series of paragraphs rendered from an array of strings.
 *
 * @param {Array} Strings to render as separate paragraphs.
 *
 * @returns	{Array} Paragraph elements.
 */
const Paragraphs = ( { children = [] } ) => {
	return children.map( ( p, i ) => <p key={ i }>{ p }</p> );
};

export default Paragraphs;
