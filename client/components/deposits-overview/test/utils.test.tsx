/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getDepositScheduleDescription, areDepositsBlocked } from '../utils';

// Mock Overview.Account object.
const mockAccount: AccountOverview.Account = {
	default_currency: 'USD',
	deposits_blocked: false,
	deposits_disabled: false,
	deposits_schedule: {
		delay_days: 0,
		interval: 'weekly',
		weekly_anchor: 'Monday',
		monthly_anchor: 15,
	},
};

// Mock the wcpaySettings localized variables needed by these tests.
declare const global: {
	wcpaySettings: {
		featureFlags: {
			customDepositSchedules: boolean;
		};
	};
};
const mockWcPaySettings = {
	featureFlags: {
		customDepositSchedules: true,
	},
};

// Tests for getDepositScheduleDescription()
describe( 'getDepositScheduleDescription', () => {
	test( 'should return the correct description for weekly deposits', () => {
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically every Monday'
		);
	} );
	test( 'should return the correct description for daily deposits', () => {
		mockAccount.deposits_schedule.interval = 'daily';
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically every day'
		);
	} );
	test( 'should return the correct description for monthly deposits', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically on the 15th of every month'
		);
	} );
	test( 'should return the correct description for the first day of the month', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 1;
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically on the 1st of every month'
		);
	} );
	test( 'should return the correct description for the 2nd day of the month', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 2;
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically on the 2nd of every month'
		);
	} );
	test( 'should return the correct description for the last day of the month', () => {
		mockAccount.deposits_schedule.interval = 'monthly';
		mockAccount.deposits_schedule.monthly_anchor = 31;
		const { container } = render(
			getDepositScheduleDescription( mockAccount )
		);

		expect( container.textContent ).toContain(
			'Your deposits are dispatched automatically on the last day of every month'
		);
	} );
} );

describe( 'areDepositsBlocked', () => {
	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	test( 'should return true if the account is blocked', () => {
		mockAccount.deposits_blocked = true;
		expect( areDepositsBlocked( mockAccount ) ).toBe( true );
	} );
} );
