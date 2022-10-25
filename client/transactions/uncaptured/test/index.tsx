/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import Authorizations, { AuthorizationsList } from '../';
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import { Authorization } from 'wcpay/types/authorizations';

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( { setIsMatching: jest.fn() } ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

jest.mock( 'data/index', () => ( {
	useAuthorizations: jest.fn(),
	useAuthorizationsSummary: jest.fn(),
	useAuthorization: jest.fn( () => ( {
		doCaptureAuthorization: jest.fn(),
	} ) ),
} ) );

const mockUseAuthorizations = useAuthorizations as jest.MockedFunction<
	typeof useAuthorizations
>;

const mockUseAuthorizationsSummary = useAuthorizationsSummary as jest.MockedFunction<
	typeof useAuthorizationsSummary
>;

declare const global: {
	wcpaySettings: {
		isSubscriptionsActive: boolean;
		featureFlags: {
			customSearch: boolean;
		};
		zeroDecimalCurrencies: string[];
		currentUserEmail: string;
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
};

const getMockAuthorizations: () => Authorization[] = () => [
	{
		created: '2020-01-02 17:46:02',
		captured: false,
		order_id: 24,
		risk_level: 2,
		amount: 1455,
		customer_email: 'good_boy@doge.com',
		customer_country: 'Kingdom of Dogs',
		customer_name: 'Good boy',
		payment_intent_id: 'pi_4242',
		charge_id: 'ch_mock',
	},
	{
		created: '2020-01-03 17:46:02',
		captured: false,
		order_id: 25,
		risk_level: 0,
		amount: 2010,
		customer_email: 'good_boy@doge.com',
		customer_country: 'Kingdom of Dogs',
		customer_name: 'Good boy',
		payment_intent_id: 'pi_4243',
		charge_id: 'ch_mock',
	},
];

describe( 'Authorizations list', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		global.wcpaySettings = {
			featureFlags: {
				customSearch: true,
			},
			isSubscriptionsActive: false,
			zeroDecimalCurrencies: [],
			currentUserEmail: 'mock@example.com',
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	test( 'renders correctly', () => {
		mockUseAuthorizations.mockReturnValue( {
			authorizations: getMockAuthorizations(),
			authorizationsError: undefined,
			isLoading: false,
		} );

		mockUseAuthorizationsSummary.mockReturnValue( {
			authorizationsSummary: {
				count: 3,
				currency: 'usd',
				store_currencies: [ 'usd' ],
				total: 300,
				totalAmount: 15000,
			},
			isLoading: false,
		} );

		const { container } = render( <AuthorizationsList /> );
		expect( container ).toMatchSnapshot();
	} );

	describe( 'filters', () => {
		let container: Element;
		let rerender: ( ui: React.ReactElement ) => void;
		beforeEach( () => {
			mockUseAuthorizations.mockReturnValue( {
				authorizations: getMockAuthorizations(),
				isLoading: false,
				authorizationsError: undefined,
			} );

			mockUseAuthorizationsSummary.mockReturnValue( {
				authorizationsSummary: {
					count: 3,
					currency: 'usd',
					store_currencies: [ 'usd' ],
					total: 300,
					totalAmount: 15000,
				},
				isLoading: false,
			} );

			( { container, rerender } = render( <Authorizations /> ) );
		} );

		function expectSortingToBe( field: string, direction: string ) {
			expect( getQuery().orderby ).toEqual( field );
			expect( getQuery().order ).toEqual( direction );
			const useTransactionsCall =
				mockUseAuthorizations.mock.calls[
					mockUseAuthorizations.mock.calls.length - 1
				];
			expect( useTransactionsCall[ 0 ].orderby ).toEqual( field );
			expect( useTransactionsCall[ 0 ].order ).toEqual( direction );
		}

		function sortBy( field: string ) {
			user.click( screen.getByRole( 'button', { name: field } ) );
			rerender( <Authorizations /> );
		}

		test( 'sorts by authorized on field', () => {
			sortBy( 'Authorized on' );
			expectSortingToBe( 'authorized_on', 'asc' );

			sortBy( 'Authorized on' );
			expectSortingToBe( 'authorized_on', 'desc' );
		} );

		test( 'sorts by capture by field', () => {
			sortBy( 'Capture by' );
			expectSortingToBe( 'capture_by', 'desc' );

			sortBy( 'Capture by' );
			expectSortingToBe( 'capture_by', 'asc' );
		} );

		test( 'renders table summary only when the authorization summary data is available', () => {
			mockUseAuthorizationsSummary.mockReturnValue( {
				authorizationsSummary: {},
				isLoading: true,
			} );

			( { container } = render( <Authorizations /> ) );
			let tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);
			expect( tableSummary ).toHaveLength( 0 );

			mockUseAuthorizationsSummary.mockReturnValue( {
				authorizationsSummary: {
					count: 3,
					currency: 'usd',
					store_currencies: [ 'usd' ],
					total: 300,
					totalAmount: 15000,
				},
				isLoading: false,
			} );

			( { container } = render( <Authorizations /> ) );
			tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);

			expect( tableSummary ).toHaveLength( 1 );
		} );
	} );
} );
