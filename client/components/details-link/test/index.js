/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import DetailsLink from '../';

describe( 'Details link', () => {
	test( 'renders transaction details with charge ID', () => {
		const link = shallow(
			<DetailsLink id="ch_mock" parentSegment="transactions" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders dispute details with ID', () => {
		const link = shallow(
			<DetailsLink id="dp_mock" parentSegment="disputes" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'empty render with no ID', () => {
		const link = shallow(
			<DetailsLink parentSegment="disputes" />
		);
		expect( link ).toMatchSnapshot();
	} );
} );
