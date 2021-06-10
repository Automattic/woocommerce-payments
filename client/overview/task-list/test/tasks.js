/** @format */

/**
 * Internal dependencies
 */
import { getTasks } from '../tasks';
import createAdditionalMethodsSetupTask from '../../../additional-methods-setup/task';

jest.mock( '../../../additional-methods-setup/task', () => jest.fn() );

jest.mock( 'utils/currency', () => {
	return {
		formatCurrency: jest
			.fn()
			.mockReturnValue(
				( amount, currency ) => `${ amount } ${ currency }`
			),
	};
} );

describe( 'getTasks()', () => {
	it( 'should include business details when flag is set', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			showUpdateDetailsTask: 'yes',
			isAccountOverviewTasksEnabled: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
					completed: false,
				} ),
			] )
		);
	} );

	it( 'should omit business details when flag is not set', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted',
				currentDeadline: 1620857083,
				pastDue: true,
				accountLink: 'http://example.com',
			},
			showUpdateDetailsTask: 'no',
			isAccountOverviewTasksEnabled: true,
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
				} ),
			] )
		);
	} );

	it( 'handles when account is complete', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'complete',
				currentDeadline: 0,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			showUpdateDetailsTask: 'yes',
			isAccountOverviewTasksEnabled: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'update-business-details',
					completed: true,
				} ),
			] )
		);
	} );

	it( 'adds WPCOM user reconnect task when the url is specified', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'complete',
			},
			wpcomReconnectUrl: 'http://example.com',
			isAccountOverviewTasksEnabled: true,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'reconnect-wpcom-user',
					completed: false,
				} ),
			] )
		);
	} );

	it( 'should omit the WPCOM user reconnect task when the url is not specified', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'complete',
			},
			wpcomReconnectUrl: null,
			isAccountOverviewTasksEnabled: true,
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( {
					key: 'reconnect-wpcom-user',
				} ),
			] )
		);
	} );

	it( 'returns the expected keys when the account overview flag is enabled', () => {
		createAdditionalMethodsSetupTask.mockReturnValue( {
			key: 'woocommerce-payments--additional-payment-methods',
		} );

		const tasks = getTasks( {
			additionalMethodsSetup: { isTaskVisible: true },
			isAccountOverviewTasksEnabled: true,
			showUpdateDetailsTask: 'yes',
			wpcomReconnectUrl: 'http://example.com',
			accountStatus: {},
			needsHttpsSetup: true,
		} );

		expect( tasks ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
				expect.objectContaining( { key: 'force-secure-checkout' } ),
				expect.objectContaining( {
					key: 'woocommerce-payments--additional-payment-methods',
				} ),
			] )
		);
	} );

	it( 'returns the expected keys when the account overview flag is disabled', () => {
		createAdditionalMethodsSetupTask.mockReturnValue( {
			key: 'woocommerce-payments--additional-payment-methods',
		} );

		const tasks = getTasks( {
			additionalMethodsSetup: { isTaskVisible: true },
			showUpdateDetailsTask: 'yes',
			wpcomReconnectUrl: 'http://example.com',
			accountStatus: {},
			needsHttpsSetup: true,
		} );

		expect( tasks ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
				expect.objectContaining( { key: 'force-secure-checkout' } ),
			] )
		);
		expect( tasks ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'woocommerce-payments--additional-payment-methods',
				} ),
			] )
		);
	} );

	describe( 'additional method setup task', () => {
		beforeEach( () => {
			createAdditionalMethodsSetupTask.mockReturnValue( {} );
			window.wcpaySettings = { additionalMethodsSetup: {} };
		} );

		afterEach( () => {
			jest.restoreAllMocks();
		} );

		it( 'renders task if `isTaskVisible` is true', () => {
			createAdditionalMethodsSetupTask.mockReturnValue( {
				key: 'woocommerce-payments--additional-payment-methods',
			} );

			const actual = getTasks( {
				additionalMethodsSetup: { isTaskVisible: true },
				accountStatus: {},
				isAccountOverviewTasksEnabled: true,
			} );

			expect( actual ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						key: 'woocommerce-payments--additional-payment-methods',
					} ),
				] )
			);
		} );

		it( 'does not render task if `isTaskVisible` is false', () => {
			createAdditionalMethodsSetupTask.mockReturnValue( {
				key: 'woocommerce-payments--additional-payment-methods',
			} );

			const actual = getTasks( {
				additionalMethodsSetup: { isTaskVisible: false },
				accountStatus: {},
				isAccountOverviewTasksEnabled: true,
			} );

			expect( actual ).toEqual(
				expect.not.arrayContaining( [
					expect.objectContaining( {
						key: 'woocommerce-payments--additional-payment-methods',
					} ),
				] )
			);
		} );
	} );
	it( 'should include a dispute resolution task', () => {
		/*eslint-disable camelcase*/
		const disputes = [
			{
				id: 123,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
			},
		];
		/*eslint-enable camelcase*/
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			disputes,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-123',
					completed: false,
					level: 3,
				} ),
			] )
		);
	} );
	it( 'should include two different dispute resolution tasks', () => {
		/*eslint-disable camelcase*/
		const disputes = [
			{
				id: 123,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
			},
			{
				id: 456,
				amount: 20,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
			},
		];
		/*eslint-enable camelcase*/
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			disputes,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-123',
					completed: false,
					level: 3,
				} ),
				expect.objectContaining( {
					key: 'dispute-resolution-456',
					completed: false,
					level: 3,
				} ),
			] )
		);
	} );
} );
