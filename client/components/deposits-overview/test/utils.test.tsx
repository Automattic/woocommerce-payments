/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { areDepositsBlocked } from '../utils';

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

describe( 'areDepositsBlocked', () => {
	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	test( 'should return true if the account is blocked', () => {
		mockAccount.deposits_blocked = true;
		expect( areDepositsBlocked( mockAccount ) ).toBe( true );
	} );
} );
