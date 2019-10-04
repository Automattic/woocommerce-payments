/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import ConnectAccountPage from '..';

describe( 'ConnectAccountPage', () => {
	test( 'should render correctly', () => {
		const page = shallow(
			<ConnectAccountPage />
		);
		expect( page ).toMatchSnapshot();
	} );
} );
