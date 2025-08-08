/** @format */

/**
 * External dependencies
 */
import React, { act } from 'react';
import { render, waitFor } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';
import apiFetch from '@wordpress/api-fetch';
import { useUserPreferences } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits, useDepositsSummary } from 'wcpay/data';
import {
	CachedDeposit,
	CachedDeposits,
	DepositsSummary,
} from 'wcpay/types/deposits';

jest.mock( 'wcpay/data', () => ( {
	useDeposits: jest.fn(),
	useDepositsSummary: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '@woocommerce/data', () => {
	const actualModule = jest.requireActual( '@woocommerce/data' );

	return {
		...actualModule,
		useUserPreferences: jest.fn(),
	};
} );

const mockDeposits = [
	{
		id: 'po_mock1',
		date: '2020-01-02 17:46:02',
		type: 'deposit',
		amount: 2000,
		status: 'paid',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
		bank_reference_key: 'mock_reference_key',
	} as CachedDeposit,
	{
		id: 'po_mock2',
		date: '2020-01-03 17:46:02',
		type: 'withdrawal',
		amount: 3000,
		status: 'pending',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
		bank_reference_key: 'mock_reference_key',
	} as CachedDeposit,
	{
		id: 'po_mock3',
		date: '2020-01-04 17:46:02',
		type: 'withdrawal',
		amount: 4000,
		status: 'paid',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
		bank_reference_key: 'mock_reference_key',
	} as CachedDeposit,
];

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currentUserEmail: string;
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
		dateFormat: string;
	};
};

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
		onHistoryChange: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const mockUseDeposits = useDeposits as jest.MockedFunction<
	typeof useDeposits
>;

const mockUseDepositsSummary = useDepositsSummary as jest.MockedFunction<
	typeof useDepositsSummary
>;

const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;

describe( 'Deposits list', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: jest.fn(),
			wc_payments_payouts_hidden_columns: '',
			isRequesting: false,
		} as any );

		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currentUserEmail: 'mock@example.com',
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
			dateFormat: 'M j Y',
		};
	} );

	// this also covers structural test for single currency.
	test( 'renders correctly with multiple currencies', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 2,
			isLoading: false,
		} );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 5000,
				store_currencies: [ 'usd', 'eur' ],
				currency: 'usd',
			} as DepositsSummary,
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly a single deposit', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 1,
			isLoading: false,
		} );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 1,
				total: 5000,
				store_currencies: [ 'usd' ],
				currency: 'usd',
			} as DepositsSummary,
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders table summary only when the deposits summary data is available', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			isLoading: false,
		} as CachedDeposits );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 30,
			} as DepositsSummary,
			isLoading: true,
		} );

		let { container } = render( <DepositsList /> );
		let tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 0 );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 100,
			} as DepositsSummary,
			isLoading: false,
		} );

		( { container } = render( <DepositsList /> ) );
		tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 1 );
	} );

	describe( 'Export button', () => {
		test( 'renders when there are one or more deposits', () => {
			mockUseDeposits.mockReturnValue( {
				deposits: mockDeposits,
				isLoading: false,
			} as CachedDeposits );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Export' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no deposits', () => {
			mockUseDeposits.mockReturnValue( {
				deposits: [],
				isLoading: false,
				depositsCount: 0,
			} as CachedDeposits );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Export' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			jest.restoreAllMocks();
			mockUseDeposits.mockReturnValue( {
				deposits: mockDeposits,
				depositsCount: 2,
				isLoading: false,
			} );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 2,
					total: 5000,
				} as DepositsSummary,
				isLoading: false,
			} );
		} );

		test( 'should fetch export after confirmation when download button is selected for unfiltered exports larger than 1000.', async () => {
			window.confirm = jest.fn( () => true );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 1100,
					total: 50000,
				} as DepositsSummary,
				isLoading: false,
			} );

			const { getByRole } = render( <DepositsList /> );
			await act( async () => {
				getByRole( 'button', { name: 'Export' } ).click();
			} );

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 1100 deposits. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () => {
				expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
				expect( mockApiFetch ).toHaveBeenCalledWith( {
					method: 'POST',
					path:
						'/wc/v3/payments/deposits/download?user_email=mock%40example.com&locale=en_US',
				} );
			} );
		} );

		test( 'should not fetch export after cancel when download button is selected for unfiltered exports larger than 1000.', async () => {
			window.confirm = jest.fn( () => false );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 1100,
					total: 50000,
				} as DepositsSummary,
				isLoading: false,
			} );

			const { getByRole } = render( <DepositsList /> );

			getByRole( 'button', { name: 'Export' } ).click();

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 1100 deposits. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () =>
				expect( mockApiFetch ).not.toHaveBeenCalled()
			);
		} );
	} );
} );
