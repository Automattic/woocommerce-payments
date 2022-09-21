/** @format */

/**
 * Internal dependencies
 */
import { getTasks, taskSort } from '../tasks';

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
		const tasks = getTasks( {
			isAccountOverviewTasksEnabled: true,
			showUpdateDetailsTask: 'yes',
			wpcomReconnectUrl: 'http://example.com',
			accountStatus: {},
		} );

		expect( tasks ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
			] )
		);
	} );

	it( 'returns the expected keys when the account overview flag is disabled', () => {
		const tasks = getTasks( {
			showUpdateDetailsTask: 'yes',
			wpcomReconnectUrl: 'http://example.com',
			accountStatus: {},
		} );

		expect( tasks ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
				expect.objectContaining( { key: 'reconnect-wpcom-user' } ),
			] )
		);
	} );

	it( 'should not include the dispute resolution task', () => {
		const numDisputesNeedingResponse = 0;
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			numDisputesNeedingResponse,
		} );

		expect( actual ).toEqual( [] );
	} );

	it( 'should include the dispute resolution task', () => {
		const numDisputesNeedingResponse = 1;
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			numDisputesNeedingResponse,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task',
					completed: false,
					level: 3,
					title: '1 disputed payment needs your response',
					additionalInfo: 'View and respond',
				} ),
			] )
		);
	} );

	it( 'should include the dispute resolution task with multiple disputes', () => {
		const numDisputesNeedingResponse = 2000;
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			numDisputesNeedingResponse,
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					key: 'dispute-resolution-task',
					completed: false,
					level: 3,
					title: '2000 disputed payments need your response',
					additionalInfo: 'View and respond',
				} ),
			] )
		);
	} );
} );

describe( 'taskSort()', () => {
	it( 'should sort the tasks', () => {
		const numDisputesNeedingResponse = 1;
		const unsortedTasks = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
			isAccountOverviewTasksEnabled: true,
			numDisputesNeedingResponse,
		} );
		unsortedTasks.unshift( {
			key: 'test-element',
			completed: true,
			level: 3,
		} );
		expect( unsortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'test-element',
				completed: true,
				level: 3,
			} )
		);
		const sortedTasks = unsortedTasks.sort( taskSort );
		expect( sortedTasks[ 0 ] ).toEqual(
			expect.objectContaining( {
				key: 'dispute-resolution-task',
				completed: false,
				level: 3,
			} )
		);
	} );
} );
