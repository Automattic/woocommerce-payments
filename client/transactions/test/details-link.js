/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import DetailsLink from '../details-link';

describe( 'Details link', () => {
	test( 'renders with charge ID', () => {
		const link = shallow(
			<DetailsLink chargeId="ch_mock" />
		);
		expect( link ).toMatchSnapshot();
	} );

	test( 'renders with no charge ID', () => {
		const link = shallow(
			<DetailsLink />
		);
		expect( link ).toMatchSnapshot();
	} );
} );
