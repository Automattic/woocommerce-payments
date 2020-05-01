/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import ClickableCell from '../';

describe( 'Clickable cell', () => {
	test( 'renders a clickable cell when a link is provided', () => {
		const cell = shallow(
			<ClickableCell href="https://example.com">Content</ClickableCell>
		);
		expect( cell ).toMatchSnapshot();
	} );

	test( 'renders a plain content when no link is provided', () => {
		const cell = shallow(
			<ClickableCell href="">Content</ClickableCell>
		);
		expect( cell ).toMatchSnapshot();
	} );
} );
