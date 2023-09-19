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

	test( 'renders pending verification status', async () => {
		const { container: depositsStatus } = renderDepositsStatus( {
			status: 'blocked',
			accountStatus: 'pending_verification',
			interval: 'daily',
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

	function renderDepositsStatus( {
		status,
		interval,
		accountStatus,
		poEnabled = false,
		poComplete = false,
		iconSize,
	} ) {
		return render(
			<DepositsStatus
				status={ status }
				accountStatus={ accountStatus }
				interval={ interval }
				poEnabled={ poEnabled }
				poComplete={ poComplete }
				iconSize={ iconSize }
			/>
		);
	}
} );
