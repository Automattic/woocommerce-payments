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
	test( 'renders transaction details with charge ID', () => {
		const link = shallow(
			<ClickableCell id="ch_mock" parentSegment="transactions">Content</ClickableCell>
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders dispute details with ID', () => {
		const link = shallow(
			<ClickableCell id="dp_mock" parentSegment="disputes">Content</ClickableCell>
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'empty render with no ID', () => {
		const link = shallow(
			<ClickableCell parentSegment="disputes">Content</ClickableCell>
		);
		expect( link ).toMatchSnapshot();
	} );
} );
