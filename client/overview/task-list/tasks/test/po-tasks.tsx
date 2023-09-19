/** @format */

/**
 * Internal dependencies
 */
import { getVerifyBankAccountTask } from '../po-task';

// Type definitions allowing us to mock the global wcpaySettings object.
declare const global: {
	wcpaySettings: {
		accountStatus: {
			status: string;
			progressiveOnboarding: {
				isEnabled: boolean;
				isComplete: boolean;
				tpv: number;
				firstTransactionDate?: string | null;
			};
			created?: string;
		};
	};
};

describe( 'getVerifyBankAccountTask()', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () =>
			new Date( '2023-02-01T12:33:37.000Z' ).getTime()
		);
	} );
	afterEach( () => {
		// roll it back
		Date.now = () => new Date().getTime();
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
				title: 'Please add your bank details to keep selling',
				level: 2,
				completed: false,
				expanded: true,
				isDismissable: false,
				actionLabel: 'Set up deposits',
				showActionButton: true,
				visible: true,
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
				title:
					'Payments paused! Verify your bank details to reactivate.',
				level: 1,
				completed: false,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Verify bank details',
				visible: true,
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
				title: 'Verify your bank account to start receiving deposits',
				level: 3,
				completed: false,
				expanded: true,
				showActionButton: true,
				actionLabel: 'Start receiving deposits',
				visible: true,
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
				title: 'Verify your bank account to start receiving deposits',
				level: 2,
				completed: false,
				visible: true,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Start receiving deposits',
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
				title: 'Verify your bank account to start receiving deposits',
				level: 2,
				completed: false,
				expanded: true,
				isDismissable: false,
				actionLabel: 'Start receiving deposits',
				showActionButton: true,
				visible: true,
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
				title: 'Verify your bank details',
				level: 1,
				completed: false,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Set up deposits',
				visible: true,
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
				title: 'Verify your bank details',
				level: 1,
				completed: false,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Set up deposits',
				visible: true,
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
				title:
					'Payments paused! Verify your bank details to reactivate.',
				level: 1,
				completed: false,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Verify bank details',
				visible: true,
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
				title:
					'Payments paused! Verify your bank details to reactivate.',
				level: 1,
				completed: false,
				expanded: true,
				isDismissable: false,
				showActionButton: true,
				actionLabel: 'Verify bank details',
				visible: true,
				time: '2 minutes',
			} )
		);
	} );
} );
