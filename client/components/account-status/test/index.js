/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountStatus from '../';
import StatusChip from '../status-chip';

describe( 'AccountStatus', () => {
	beforeEach( () => {
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	test( 'renders error status', () => {
		const { container: accountStatus } = renderAccountStatus(
			{ error: 'Test error' },
			{}
		);
		expect( accountStatus ).toMatchSnapshot();
	} );

	test( 'renders normal status', () => {
		const { container: accountStatus } = renderAccountStatus(
			{
				status: 'complete',
				paymentsEnabled: 1,
				depositsStatus: 'weekly',
			},
			[
				{
					payment_method: 'card',
					fee: {
						base: {
							currency: 'EUR',
							percentage_rate: 0.029,
							fixed_rate: 0.3,
						},
						discount: [
							{
								end_time: null,
								volume_allowance: null,
								volume_currency: null,
								current_volume: null,
								percentage_rate: 0.029,
								fixed_rate: 30,
							},
						],
					},
				},
			]
		);
		expect( accountStatus ).toMatchSnapshot();
	} );

	function renderAccountStatus( accountStatus, accountFees ) {
		return render(
			<AccountStatus
				accountStatus={ accountStatus }
				accountFees={ accountFees }
			/>
		);
	}
} );

describe( 'StatusChip', () => {
	test( 'renders complete status', () => {
		const { container: statusChip } = renderStatusChip( 'complete' );
		expect( statusChip ).toMatchSnapshot();
	} );

	test( 'renders restricted soon status', () => {
		const { container: statusChip } = renderStatusChip( 'restricted_soon' );
		expect( statusChip ).toMatchSnapshot();
	} );

	test( 'renders restricted status', () => {
		const { container: statusChip } = renderStatusChip( 'restricted' );
		expect( statusChip ).toMatchSnapshot();
	} );

	test( 'renders rejected status', () => {
		const { container: statusChip } = renderStatusChip( 'rejected_' );
		expect( statusChip ).toMatchSnapshot();
	} );

	test( 'renders unknown status', () => {
		const { container: statusChip } = renderStatusChip( 'foobar' );
		expect( statusChip ).toMatchSnapshot();
	} );

	function renderStatusChip( accountStatus ) {
		return render( <StatusChip accountStatus={ accountStatus } /> );
	}
} );
