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
	beforeEach( () => {
		window.location.assign = jest.fn();
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			strings: {
				setupHeadings: [ 'heading line 1', 'heading line 2' ],
			},
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
