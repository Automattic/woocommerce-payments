/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Paragraphs from '../';

describe( 'Paragraphs', () => {
	test( 'renders set of strings correctly.', () => {
		const strings = [ 'Paragraph 1', 'Paragraph 2', 'Paragraph 3' ];
		expect( shallow( <Paragraphs>{ strings }</Paragraphs> ) ).toMatchSnapshot();
	} );

	test( 'renders empty set of strings correctly', () => {
		const strings = [];
		expect( shallow( <Paragraphs>{ strings }</Paragraphs> ) ).toMatchSnapshot();
	} );

	test( 'renders missing strings correctly', () => {
		expect( shallow( <Paragraphs /> ) ).toMatchSnapshot();
	} );
} );
