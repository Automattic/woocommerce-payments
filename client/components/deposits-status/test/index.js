/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DepositsStatus from '../';

describe( 'DepositsStatus', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			featureFlags: {
				customDepositSchedules: false,
			},
		};
	} );

	test( 'renders disabled status', () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'disabled',
			iconSize: 20,
		} );
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders daily status', () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'enabled',
			interval: 'daily',
			iconSize: 20,
		} );
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders weekly status', () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'enabled',
			interval: 'weekly',
			iconSize: 20,
		} );
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders monthly status', () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'enabled',
			interval: 'monthly',
			iconSize: 20,
		} );
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders manual status', async () => {
		const { container: depositsStatus, findByText } = renderDepositsStatus(
			{
				status: 'enabled',
				interval: 'manual',
				iconSize: 20,
			}
		);
		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders blocked status', async () => {
		const { container: depositsStatus, findByText } = renderDepositsStatus(
			{
				status: 'blocked',
				interval: 'daily',
				iconSize: 20,
			}
		);

		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders blocked status with feature flag enabled', async () => {
		// Enable custom deposit schedules feature flag.
		global.wcpaySettings.featureFlags.customDepositSchedules = true;

		const { container: depositsStatus, findByText } = renderDepositsStatus(
			{
				status: 'blocked',
				interval: 'daily',
				iconSize: 20,
			}
		);

		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders manual status with feature flag enabled', async () => {
		// Enable custom deposit schedules feature flag.
		global.wcpaySettings.featureFlags.customDepositSchedules = true;

		const { container: depositsStatus, findByText } = renderDepositsStatus(
			{
				status: 'enabled',
				interval: 'manual',
				iconSize: 20,
			}
		);

		expect( await findByText( /Manual/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders unknown status', () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'foobar',
			interval: 'foobar',
			iconSize: 20,
		} );
		expect( depositsStatus ).toMatchSnapshot();
	} );

	function renderDepositsStatus( { status, interval, iconSize } ) {
		return render(
			<DepositsStatus
				status={ status }
				interval={ interval }
				iconSize={ iconSize }
			/>
		);
	}
} );
