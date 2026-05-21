/** @format */

/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react-hooks';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from '../hooks';
import { STORE_NAME } from '../../constants';
import balanceSummaryFixture from './balance-fixture';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

const mockUseSelect = useSelect as jest.Mock;
const getReportsBalanceSummary = jest.fn();
const getReportsBalanceSummaryError = jest.fn();
const isResolving = jest.fn();

declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: string;
	};
};

const period = {
	start: '2024-03-01T00:00:00',
	end: '2024-03-31T23:59:59',
};

const renderBalanceHook = () =>
	renderHook( () => useReportsBalanceSummary( period ) );

describe( 'useReportsBalanceSummary', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountDefaultCurrency: 'USD',
		};
		getReportsBalanceSummary.mockReset();
		getReportsBalanceSummaryError.mockReset();
		isResolving.mockReset();
		mockUseSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( ( storeName: string ) => {
				expect( storeName ).toBe( STORE_NAME );
				return {
					getReportsBalanceSummary,
					getReportsBalanceSummaryError,
					isResolving,
				};
			} )
		);
	} );

	it( 'returns loading state while the Balance summary is resolving', () => {
		getReportsBalanceSummary.mockReturnValue( {} );
		getReportsBalanceSummaryError.mockReturnValue( {} );
		isResolving.mockReturnValue( true );

		const { result } = renderBalanceHook();

		expect( result.current ).toEqual( {
			summary: {},
			error: {},
			isLoading: true,
		} );
	} );

	it( 'returns resolved Balance summary data', () => {
		getReportsBalanceSummary.mockReturnValue( balanceSummaryFixture );
		getReportsBalanceSummaryError.mockReturnValue( {} );
		isResolving.mockReturnValue( false );

		const { result } = renderBalanceHook();

		expect( result.current ).toEqual( {
			summary: balanceSummaryFixture,
			error: {},
			isLoading: false,
		} );
	} );

	it( 'uses the account default currency in the resolver query', () => {
		getReportsBalanceSummary.mockReturnValue( balanceSummaryFixture );
		getReportsBalanceSummaryError.mockReturnValue( {} );
		isResolving.mockReturnValue( false );

		renderBalanceHook();

		const expectedQuery = {
			dateStart: '2024-03-01T00:00:00',
			dateEnd: '2024-03-31T23:59:59',
			currency: 'usd',
		};
		expect( getReportsBalanceSummary ).toHaveBeenCalledWith(
			expectedQuery
		);
		expect( getReportsBalanceSummaryError ).toHaveBeenCalledWith(
			expectedQuery
		);
		expect( isResolving ).toHaveBeenCalledWith(
			'getReportsBalanceSummary',
			[ expectedQuery ]
		);
	} );
} );
