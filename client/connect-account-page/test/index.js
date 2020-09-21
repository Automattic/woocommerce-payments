/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ConnectAccountPage from '..';

describe( 'ConnectAccountPage', () => {
	beforeEach( () => {
		window.location.assign = jest.fn();
		window.wcTracks.isEnabled = false;
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
		};
	} );

	test( 'should render correctly', () => {
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render correctly when on-boarding disabled', () => {
		global.wcpaySettings.onBoardingDisabled = true;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render properly when site usage tracking is enabled', () => {
		window.wcTracks.isEnabled = true;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );
} );
