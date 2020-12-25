/** @format **/
/**
 * External dependencies
 */
import { ReactNode } from 'react';

/**
 * Series of paragraphs rendered from an array of strings.
 *
 * @param {ReactNode[]} children Strings to render as separate paragraphs.
 *
 * @returns	{ReactNode[]} Paragraph elements.
 */
const Paragraphs = ( { children = [] } ) => {
	return children.map( ( p, i ) => <p key={ i }>{ p }</p> );
};

export default Paragraphs;
