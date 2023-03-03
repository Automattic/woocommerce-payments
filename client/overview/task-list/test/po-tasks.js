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
		const actual = getVerifyBankAccountTask( {
			poEnabled: false,
			poComplete: false,
			tpv: 0,
			firstPaymentDate: null,
		} );

		expect( actual ).toBeNull();
	} );
	it( 'should return null when po is complete', () => {
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: true,
			tpv: 0,
			firstPaymentDate: null,
		} );

		expect( actual ).toBeNull();
	} );
	it( 'should return null when first payment date is null', () => {
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 0,
			firstPaymentDate: null,
		} );

		expect( actual ).toBeNull();
	} );
	it( 'should return the correct task when first payment is done', () => {
		const actual = getVerifyBankAccountTask( {
			poEnabled: true,
			poComplete: false,
			tpv: 100,
			firstPaymentDate: '2023-02-02',
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
