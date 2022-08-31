/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { RiskLevel } from 'wcpay/types/authorizations';
import { getResourceId } from 'utils/data';

describe( 'Authorizations reducer', () => {
	const mockQuery = { paged: '1', perPage: '50' };
	const mockAuthorizations = [
		{
			authorization_id: 'id_1661935621753_995',
			authorized_on: 'Aug 31, 2022 / 8:47AM',
			capture_by: 'Sep 7, 2022 / 8:47AM',
			order: {
				number: 254,
				customer_url: 'https://doggo.com',
				url: 'https://doggo.com',
			},
			risk_level: 'elevated' as RiskLevel,
			amount: 4654,
			customer_email: 'good_boy@doge.com',
			customer_country: 'Kingdom of Dogs',
			customer_name: 'Good boy',
			payment_intent_id: 'pi_3Lcm2iQsDOQXPzI102uKS0FD',
		},
		{
			authorization_id: 'id_1661935621753_107',
			authorized_on: 'Aug 31, 2022 / 8:47AM',
			capture_by: 'Sep 7, 2022 / 8:47AM',
			order: {
				number: 254,
				customer_url: 'https://doggo.com',
				url: 'https://doggo.com',
			},
			risk_level: 'normal' as RiskLevel,
			amount: 4906,
			customer_email: 'good_boy@doge.com',
			customer_country: 'Kingdom of Dogs',
			customer_name: 'Good boy',
			payment_intent_id: 'pi_3Lcm2iQsDOQXPzI102uKS0FD',
		},
	];

	test( 'should reduce new authorizations correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_AUTHORIZATIONS,
				data: mockAuthorizations,
				query: mockQuery,
			}
		);

		const after = {
			byId: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			summary: {},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce updated authorizations correctly', () => {
		const before = {
			byId: {},
			earlierQuery: {
				data: mockAuthorizations[ 0 ],
			},
			summary: {},
		};

		const reduced = reducer( before, {
			type: types.SET_AUTHORIZATIONS,
			data: mockAuthorizations.slice( 1 ),
			query: mockQuery,
		} );

		const after = {
			byId: {},
			earlierQuery: {
				data: mockAuthorizations[ 0 ],
			},
			summary: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations.slice( 1 ),
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce authorizations summary correctly', () => {
		const reduced = reducer( undefined, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			query: mockQuery,
			data: {
				count: 42,
			},
		} );

		const after = {
			byId: {},
			summary: {
				count: 42,
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce updated authorizations summary correctly', () => {
		const before = {
			byId: {},
			summary: {
				count: 42,
			},
		};

		const reduced = reducer( before, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			query: mockQuery,
			data: {
				count: 4242,
			},
		} );

		const after = {
			...before,
			summary: {
				count: 4242,
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce new authorization correctly', () => {
		const stateAfterOne = reducer( undefined, {
			type: types.SET_AUTHORIZATION,
			data: mockAuthorizations[ 0 ],
		} );

		expect( stateAfterOne ).toStrictEqual( {
			byId: {
				id_1661935621753_995: mockAuthorizations[ 0 ],
			},
			summary: {},
		} );

		const stateAfterTwo = reducer( stateAfterOne, {
			type: types.SET_AUTHORIZATION,
			data: mockAuthorizations[ 1 ],
		} );

		expect( stateAfterTwo ).toStrictEqual( {
			byId: {
				id_1661935621753_995: mockAuthorizations[ 0 ],
				id_1661935621753_107: mockAuthorizations[ 1 ],
			},
			summary: {},
		} );
	} );
} );
