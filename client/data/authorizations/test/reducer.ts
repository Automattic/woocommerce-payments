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
			payment_intent_id: 'pi_7890',
			amount: 1000,
			order_id: 950,
			created: 'Today',
			risk_level: 0,
			customer_name: 'Test',
			customer_email: 'test@example.com',
			customer_country: 'US',
		},
		{
			payment_intent_id: 'pi_1235',
			amount: 2000,
			order_id: 100,
			created: 'Today',
			risk_level: 0,
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
		expect( reducer( emptyState, { type: 'WRONG-TYPE' } ) ).toBe(
			emptyState
		);
		expect( reducer( filledState, { type: 'WRONG-TYPE' } ) ).toBe(
			filledState
		);
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

	test( 'New transactions summary reduced correctly', () => {
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

	test( 'Transactions summary updated correctly on updated info', () => {
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
