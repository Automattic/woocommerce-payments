/** @format */

/**
 * Internal dependencies
 */
import { getVerifyBankAccountTask } from '../po-tasks';

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
		wcpaySettings.accountStatus = {
			progressiveOnboarding: {
				isEnabled: false,
				isComplete: false,
				tpv: 0,
				firstTransactionDate: null,
			},
		};

		expect( getVerifyBankAccountTask() ).toBeNull();
	} );
	it( 'should return null when po is complete', () => {
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: true,
			tpv: 0,
			firstPaymentDate: null,
			createdDate: '2023-01-31',
		} );

		expect( actual ).toBeNull();
	} );
	it( 'should return the correct task when po is enabled and incomplete and 14 days after no payment', () => {
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 1100,
			firstPaymentDate: null,
			createdDate: '2023-01-14',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 1100,
			firstPaymentDate: null,
			createdDate: '2023-01-01',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 100,
			firstPaymentDate: '2023-02-02',
			createdDate: '2023-01-31',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 1100,
			firstPaymentDate: '2023-02-02',
			createdDate: '2023-01-31',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 100,
			firstPaymentDate: '2023-01-23',
			createdDate: '2023-01-22',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 3100,
			firstPaymentDate: '2023-02-02',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 100,
			firstPaymentDate: '2023-01-10',
			createdDate: '2023-01-09',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 5100,
			firstPaymentDate: '2023-02-02',
			createdDate: '2023-01-31',
		} );

		expect( actual ).toEqual(
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
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 100,
			firstPaymentDate: '2023-01-01',
			createdDate: '2022-12-31',
		} );

		expect( actual ).toEqual(
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
