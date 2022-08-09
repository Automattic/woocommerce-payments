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
		const { container: depositsStatus } = renderDepositsStatus(
			'disabled',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders daily status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'daily',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders weekly status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'weekly',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders monthly status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'monthly',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders manual status', async () => {
		const { container: depositsStatus, findByText } = renderDepositsStatus(
			'manual',
			20
		);
		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders blocked status', async () => {
		const { container: depositsStatus, findByText } = renderDepositsStatus(
			'blocked',
			20
		);

		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders blocked status with feature flag enabled', async () => {
		// Enable custom deposit schedules feature flag.
		global.wcpaySettings.featureFlags.customDepositSchedules = true;

		const { container: depositsStatus, findByText } = renderDepositsStatus(
			'blocked',
			20
		);

		expect( await findByText( /Temporarily suspended/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders manual status with feature flag enabled', async () => {
		// Enable custom deposit schedules feature flag.
		global.wcpaySettings.featureFlags.customDepositSchedules = true;

		const { container: depositsStatus, findByText } = renderDepositsStatus(
			'manual',
			20
		);

		expect( await findByText( /Manual/i ) ).toBeVisible();
		expect( depositsStatus ).toMatchSnapshot();
	} );

	test( 'renders unknown status', () => {
		const { container: depositsStatus } = renderDepositsStatus(
			'foobar',
			20
		);
		expect( depositsStatus ).toMatchSnapshot();
	} );

	function renderDepositsStatus( depositsStatus, iconSize ) {
		return render(
			<DepositsStatus
				depositsStatus={ depositsStatus }
				iconSize={ iconSize }
			/>
		);
	}
} );
