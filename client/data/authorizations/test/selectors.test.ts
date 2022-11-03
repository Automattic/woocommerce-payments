/** @format */

/**
 * External dependencies
 */
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import {
	getAuthorization,
	getAuthorizations,
	getAuthorizationsError,
	getAuthorizationsSummary,
	getAuthorizationsSummaryError,
} from '../selectors';
import {
	AuthorizationsSummary,
	Authorization,
} from 'wcpay/types/authorizations';
import authorizationsFixture from './authorizations.fixture.json';
import authorizationsSummaryFixture from './authorizations-summary.fixture.json';

describe( 'Authorizations selector', () => {
	const mockQuery: Query = { paged: '2', per_page: '50' };
	const mockSummaryQuery: Query = {};
	const mockAuthorizations: Authorization[] = authorizationsFixture;

	const mockSummary: AuthorizationsSummary = authorizationsSummaryFixture;

	const mockError = {
		error: 'Something went wrong!',
		code: 400,
	};

	// Sections in initial state are empty.
	const emptyState = {
		authorizations: {
			summary: {},
		},
	};
	const emptySummaryErrorState = {
		authorizations: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					error: {},
				},
			},
		},
	};

	// State is populated.
	const filledSuccessState = {
		authorizations: {
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					data: mockSummary,
				},
			},
		},
	};
	const filledErrorState = {
		authorizations: {
			[ getResourceId( mockQuery ) ]: {
				error: mockError,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					error: mockError,
				},
			},
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

	test( 'Returns empty authorizations list error when error is empty', () => {
		expect( getAuthorizationsError( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'Returns authorizations list error from state', () => {
		const expected = mockError;
		expect( getAuthorizationsError( filledErrorState, mockQuery ) ).toBe(
			expected
		);
	} );

	test( 'Returns empty authorizations summary when authorizations summary is empty', () => {
		expect(
			getAuthorizationsSummary( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns authorizations summary from state', () => {
		const expected = mockSummary;
		expect(
			getAuthorizationsSummary( filledSuccessState, mockSummaryQuery )
		).toBe( expected );
	} );

	test( 'Returns empty authorizations summary error when state is uninitialized', () => {
		expect(
			getAuthorizationsSummaryError( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns empty authorizations summary error when error is empty', () => {
		expect(
			getAuthorizationsSummaryError(
				emptySummaryErrorState,
				mockSummaryQuery
			)
		).toStrictEqual( {} );
	} );

	test( 'Returns authorizations summary error from state', () => {
		const expected = mockError;
		expect(
			getAuthorizationsSummaryError( filledErrorState, mockSummaryQuery )
		).toBe( expected );
	} );
} );

describe( 'Authorization selector', () => {
	const mockAuthorization = authorizationsFixture[ 0 ];

	const emptyState = { authorizations: { byId: {}, summary: {} } };

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
