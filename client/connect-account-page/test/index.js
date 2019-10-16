/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ConnectAccountPage from '..';

describe( 'ConnectAccountPage', () => {
	const wcpayConnectUrl = '/wcpay-connect-url';
	beforeEach( () => {
		window.location.assign = jest.fn();
		global.wcpaySettings = {
			connectUrl: wcpayConnectUrl,
		};
	} );

	test( 'should render correctly', () => {
		const page = shallow( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );
} );
