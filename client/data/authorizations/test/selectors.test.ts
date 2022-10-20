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
import authorizationsFixture from './authorizations.fixture.json';
import authorizationsSummaryFixture from './authorizations-summary.fixture.json';

const emptyState = { authorizations: {} };

describe( 'Authorizations selector', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockAuthorizations = authorizationsFixture;

	const filledSuccessState = {
		authorizations: {
			byId: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			summary: {},
		},
	};

	test( 'Returns empty authorizations list when authorizations list is empty', () => {
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
	const mockAuthorizationsSummary = authorizationsSummaryFixture;

	// State is populated.
	const filledSuccessState = {
		authorizations: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockAuthorizationsSummary,
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
		).toStrictEqual( mockAuthorizationsSummary );
	} );
} );

describe( 'Authorization selector', () => {
	const mockAuthorization = authorizationsFixture[ 0 ];

	const filledState = {
		authorizations: {
			byId: {
				[ mockAuthorization.payment_intent_id ]: mockAuthorization,
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
			getAuthorization( filledState, mockAuthorization.payment_intent_id )
		).toStrictEqual( mockAuthorization );
	} );
} );
