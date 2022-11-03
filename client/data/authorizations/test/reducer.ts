/** @format */
/**
 * External dependencies
 */
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';
import {
	AuthorizationsSummary,
	Authorization,
} from 'wcpay/types/authorizations';

describe( 'Authorizations reducer tests', () => {
	const mockQuery: Query = { paged: '2', per_page: '50' };
	const mockAuthorizations: Authorization[] = [
		{
			authorization_id: 'pi_7890',
			amount: 1000,
			order: {
				number: 456,
				url: 'https://test.com',
				customer_url: 'https://customer.com',
			},
			authorized_on: 'Today',
			capture_by: 'Tomorrow',
			risk_level: 'high',
			customer_name: 'Test',
			customer_email: 'test@example.com',
			customer_country: 'US',
		},
		{
			authorization_id: 'pi_1235',
			amount: 2000,
			order: {
				number: 123,
				url: 'https://test.com',
				customer_url: 'https://customer.com',
			},
			authorized_on: 'Today',
			capture_by: 'Tomorrow',
			risk_level: 'high',
			customer_name: 'Test',
			customer_email: 'test@example.com',
			customer_country: 'US',
		},
	];
	const mockSummary: AuthorizationsSummary = {
		total: 1000,
		count: 950,
	};

	const emptyState = { summary: {} };
	const filledState = {
		[ getResourceId( mockQuery ) ]: {
			data: mockAuthorizations,
		},
		summary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockSummary,
			},
		},
	};

	test( 'Unrelated action is ignored', () => {
		const mockAction = {
			type: 'WRONG-TYPE',
			data: mockAuthorizations.slice( 0 ),
			query: {},
		};

		expect( reducer( emptyState, mockAction ) ).toBe( emptyState );
		expect( reducer( filledState, mockAction ) ).toBe( filledState );
	} );

	test( 'New authorizations reduced correctly', () => {
		// Set up mock data
		const expected = {
			...emptyState,
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_AUTHORIZATIONS,
			data: mockAuthorizations,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Authorizations updated correctly on updated info', () => {
		const newAuthorizations: Authorization[] = [
			...mockAuthorizations,
			...mockAuthorizations,
		];

		const expected = {
			...filledState,
			[ getResourceId( mockQuery ) ]: {
				data: newAuthorizations,
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_AUTHORIZATIONS,
			data: newAuthorizations,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'New authorizations summary reduced correctly', () => {
		const expected = {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockSummary,
				},
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			data: mockSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Authorizations summary updated correctly on updated info', () => {
		const newSummary = {
			total: 5000,
			fees: 100,
			net: 4900,
		};

		const expected = {
			...filledState,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: newSummary,
				},
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			data: newSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );
} );
