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
	const setupHeading = 'setup-heading';
	const wcpayConnectUrl = '/wcpay-connect-url';
	const tosUrl = '/tos-url';
	beforeEach( () => {
		window.location.assign = jest.fn();
		global.wcpaySettings = {
			connectUrl: wcpayConnectUrl,
			strings: {
				setupHeading: setupHeading,
			},
			tosUrl: tosUrl,
		};
	} );

	test( 'should render correctly', () => {
		const page = shallow( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render correctly when on-boarding disabled', () => {
		global.wcpaySettings.onBoardingDisabled = true;
		const page = shallow( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );
} );
