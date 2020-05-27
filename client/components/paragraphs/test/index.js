/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Paragraphs from '../';

describe( 'Paragraphs', () => {
	test( 'renders set of strings correctly.', () => {
		const strings = [ 'Paragraph 1', 'Paragraph 2', 'Paragraph 3' ];
		const { container } = render( <Paragraphs>{ strings }</Paragraphs> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders empty set of strings correctly', () => {
		const strings = [];
		const { container } = render( <Paragraphs>{ strings }</Paragraphs> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders missing strings correctly', () => {
		const { container } = render( <Paragraphs /> );
		expect( container ).toMatchSnapshot();
	} );
} );
