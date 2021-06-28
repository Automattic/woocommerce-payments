/** @format */

/**
 * Internal dependencies
 */
import { getTasks, taskSort } from '../tasks';

jest.mock( 'utils/currency', () => {
	return {
		formatCurrency: jest.fn().mockReturnValue( () => '10 USD' ),
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
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( {
					key: 'reconnect-wpcom-user',
				} ),
			] )
		);
	} );
	it( 'should include a dispute resolution task', () => {
		const disputes = [
			{
				id: 123,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
				status: 'needs_response',
			},
		];
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
		const disputes = [
			{
				id: 456,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
				status: 'needs_response',
			},
			{
				id: 789,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
				status: 'won',
			},
		];
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
					key: 'dispute-resolution-456',
					completed: false,
					level: 3,
				} ),
				expect.objectContaining( {
					key: 'dispute-resolution-789',
					completed: true,
					level: 3,
				} ),
			] )
		);
	} );
} );

describe( 'taskSort()', () => {
	it( 'should sort the tasks', () => {
		/*eslint-disable camelcase*/
		const disputes = [
			{
				id: 123,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
				status: 'won',
			},
			{
				id: 456,
				amount: 10,
				currency: 'USD',
				evidence_details: { due_by: 1624147199 },
				status: 'needs_response',
			},
		];
		/*eslint-enable camelcase*/
		const unsortedTasks = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			disputes,
		} );
		expect( unsortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'dispute-resolution-123',
				completed: true,
				level: 3,
			} )
		);
		const sortedTasks = unsortedTasks.sort( taskSort );
		expect( sortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'dispute-resolution-456',
				completed: false,
				level: 3,
			} )
		);
	} );
} );
