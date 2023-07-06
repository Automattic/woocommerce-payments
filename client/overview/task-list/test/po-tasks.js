/** @format */

/**
 * Internal dependencies
 */
import { getVerifyBankAccountTask } from '../tasks/po-task';

describe( 'getVerifyBankAccountTask()', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () => new Date( '2023-02-01T12:33:37.000Z' ) );
	} );
	afterEach( () => {
		// roll it back
		Date.now = () => new Date();
	} );
	it( 'should return null when po is not enabled', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: false,
					isComplete: false,
					tpv: 0,
					firstTransactionDate: null,
				},
			},
		};

		expect( getVerifyBankAccountTask() ).toBeNull();
	} );
	it( 'should return null when po is complete', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'complete',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: true,
					tpv: 0,
					firstTransactionDate: null,
				},
			},
		};

		expect( getVerifyBankAccountTask() ).toBeNull();
	} );
	it( 'should return null when status is pending', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'pending',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 10000,
					firstTransactionDate: '2023-02-02',
				},
				created: '2023-01-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toBeNull();
	} );
	it( 'should return the correct task when po is enabled and incomplete and 14 days after no payment', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 110000,
					firstTransactionDate: null,
				},
				created: '2023-01-14',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 2,
				title: 'Please add your bank details to keep selling',
				completed: false,
				actionLabel: 'Set up deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and 30 days after no payment', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 110000,
					firstTransactionDate: null,
				},
				created: '2023-01-01',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 1,
				title:
					'Payments paused! Verify your bank details to reactivate.',
				completed: false,
				actionLabel: 'Verify bank details',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when first payment is done', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 10000,
					firstTransactionDate: '2023-02-02',
				},
				created: '2023-01-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 3,
				title: 'Verify your bank account to start receiving deposits',
				completed: false,
				actionLabel: 'Start receiving deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and tpv is rising', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 110000,
					firstTransactionDate: '2023-02-02',
				},
				created: '2023-01-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 2,
				title: 'Verify your bank account to start receiving deposits',
				completed: false,
				actionLabel: 'Start receiving deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and T+7 after payment', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 10000,
					firstTransactionDate: '2023-01-23',
				},
				created: '2023-01-22',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 2,
				title: 'Verify your bank account to start receiving deposits',
				completed: false,
				actionLabel: 'Start receiving deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and tpv is near thresholds', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 310000,
					firstTransactionDate: '2023-02-02',
				},
				created: '2023-01-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 1,
				title: 'Verify your bank details',
				completed: false,
				actionLabel: 'Set up deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and T+21 after payment', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted_soon',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 10000,
					firstTransactionDate: '2023-01-10',
				},
				created: '2023-01-09',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 1,
				title: 'Verify your bank details',
				completed: false,
				actionLabel: 'Set up deposits',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and tpv reached thresholds', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 510000,
					firstTransactionDate: '2023-02-02',
				},
				created: '2023-01-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 1,
				title:
					'Payments paused! Verify your bank details to reactivate.',
				completed: false,
				actionLabel: 'Verify bank details',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
	it( 'should return the correct task when po is enabled and incomplete and T+30 after payment', () => {
		global.wcpaySettings = {
			accountStatus: {
				status: 'restricted',
				progressiveOnboarding: {
					isEnabled: true,
					isComplete: false,
					tpv: 10000,
					firstTransactionDate: '2023-01-01',
				},
				created: '2022-12-31',
			},
		};

		expect( getVerifyBankAccountTask() ).toEqual(
			expect.objectContaining( {
				key: 'verify-bank-details-po',
				level: 1,
				title:
					'Payments paused! Verify your bank details to reactivate.',
				completed: false,
				actionLabel: 'Verify bank details',
				visible: true,
				expandable: true,
				expanded: true,
				showActionButton: true,
				time: '2 minutes',
			} )
		);
	} );
} );
