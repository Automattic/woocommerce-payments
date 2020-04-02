/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import DetailsIcon from '../';

describe( 'Details link', () => {
	test( 'renders transaction details with charge ID', () => {
		const icon = shallow(
			<DetailsIcon id="ch_mock" parentSegment="transactions" />
		);
		expect( icon ).toMatchSnapshot();
	} );

	test( 'renders dispute details with ID', () => {
		const icon = shallow(
			<DetailsIcon id="dp_mock" parentSegment="disputes" />
		);
		expect( icon ).toMatchSnapshot();
	} );

	test( 'empty render with no ID', () => {
		const icon = shallow(
			<DetailsIcon parentSegment="disputes" />
		);
		expect( icon ).toMatchSnapshot();
	} );
} );
