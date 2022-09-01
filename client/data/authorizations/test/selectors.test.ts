/** @format */

/**
 * Internal dependencies
 */
import {
	getAuthorization,
	getAuthorizations,
	getAuthorizationsSummary,
} from '../selectors';
import { getResourceId } from 'utils/data';
import { RiskLevel } from 'wcpay/types/authorizations';

const emptyState = { authorizations: {} };

describe( 'Authorizations selector', () => {
	const mockQuery = { paged: '2', perPage: '50' };
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

	const filledSuccessState = {
		authorizations: {
			byId: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			summary: {},
		},
	};

	test( 'Returns empty disputes list when disputes list is empty', () => {
		expect( getAuthorizations( emptyState, mockQuery ) ).toStrictEqual(
			[]
		);
	} );

	test( 'Returns authorizations list from state', () => {
		const expected = mockAuthorizations;
		expect(
			getAuthorizations( filledSuccessState, mockQuery )
		).toStrictEqual( expected );
	} );
} );

describe( 'Authorizations summary selector', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDisputesSummary = {
		count: 42,
	};

	// State is populated.
	const filledSuccessState = {
		authorizations: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 42,
					},
				},
			},
		},
	};

	test( 'Returns empty authorizations summary when state is empty', () => {
		expect(
			getAuthorizationsSummary( emptyState, mockQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns Authorizations summary from state', () => {
		expect(
			getAuthorizationsSummary( filledSuccessState, mockQuery )
		).toStrictEqual( mockDisputesSummary );
	} );
} );

describe( 'Authorization selector', () => {
	const mockAuthorization = {
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
	};

	const filledState = {
		authorizations: {
			byId: {
				id_1661935621753_995: mockAuthorization,
			},
		},
	};

	test( 'Returns undefined when authorization is not present', () => {
		expect(
			getAuthorization( emptyState, 'id_1661935621753_995' )
		).toStrictEqual( undefined );
	} );

	test( 'Returns authorization when it is present', () => {
		expect(
			getAuthorization( filledState, 'id_1661935621753_995' )
		).toStrictEqual( mockAuthorization );
	} );
} );
