/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render, waitFor, screen } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import TransactionsPage from '../';
import {
	useTransactions,
	useTransactionsSummary,
	useSettings,
	useManualCapture,
	useAuthorizationsSummary,
} from 'data/index';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

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
	useTransactions: jest.fn(),
	useTransactionsSummary: jest.fn(),
	useManualCapture: jest.fn(),
	useSettings: jest.fn(),
	useAuthorizationsSummary: jest.fn(),
} ) );

const mockUseTransactions = useTransactions as jest.MockedFunction<
	typeof useTransactions
>;

const mockUseTransactionsSummary = useTransactionsSummary as jest.MockedFunction<
	typeof useTransactionsSummary
>;

const mockUseSettings = useSettings as jest.MockedFunction<
	typeof useSettings
>;

const mockUseManualCapture = useManualCapture as jest.MockedFunction<
	typeof useManualCapture
>;

const mockUseAuthorizationsSummary = useAuthorizationsSummary as jest.MockedFunction<
	typeof useAuthorizationsSummary
>;

declare const global: {
	wcpaySettings: {
		featureFlags: {
			customSearch: boolean;
			isAuthAndCaptureEnabled: boolean;
		};
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		accountStatus: {
			status: boolean;
		};
	};
};

describe( 'TransactionsPage', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		mockUseSettings.mockReturnValue( {
			isLoading: false,
			isSaving: false,
			settings: {},
			saveSettings: ( a ) => a,
		} );

		mockUseTransactions.mockReturnValue( {
			isLoading: false,
			transactions: [],
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			isLoading: false,
			transactionsSummary: {
				count: 10,
				total: 15,
			},
		} );

		global.wcpaySettings = {
			featureFlags: {
				customSearch: true,
				isAuthAndCaptureEnabled: true,
			},
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			accountStatus: {
				status: true,
			},
		};
	} );

	const renderTransactionsPage = async () => {
		const renderResult = render( <TransactionsPage /> );

		await waitFor( () => {
			expect( mockUseAuthorizationsSummary ).toHaveBeenCalled();
		} );

		return renderResult;
	};

	test( 'renders uncaptured tab if auth&capture is DISABLED but authorizations are present', async () => {
		mockUseManualCapture.mockReturnValue( [ false ] );
		mockUseAuthorizationsSummary.mockReturnValue( {
			authorizationsSummary: {
				total: 5,
			},
			isLoading: false,
		} );

		await renderTransactionsPage();
		expect( screen.queryByText( 'Uncaptured' ) ).toBeInTheDocument();
	} );

	test( 'renders uncaptured tab if auth&capture is ENABLED and authorizations are present', async () => {
		mockUseManualCapture.mockReturnValue( [ true ] );
		mockUseAuthorizationsSummary.mockReturnValue( {
			authorizationsSummary: {
				total: 5,
			},
			isLoading: false,
		} );

		expect( screen.queryByText( 'Uncaptured' ) ).toBeInTheDocument();
	} );

	test( 'renders uncaptured tab if auth&capture is ENABLED and no authorizations are present', async () => {
		mockUseManualCapture.mockReturnValue( [ false ] );
		mockUseAuthorizationsSummary.mockReturnValue( {
			authorizationsSummary: {
				total: 5,
			},
			isLoading: false,
		} );

		await renderTransactionsPage();
		expect( screen.queryByText( 'Uncaptured' ) ).toBeInTheDocument();
	} );

	test( 'do not render uncaptured tab if auth&capture is DISABLED and no authorizations are present', async () => {
		mockUseManualCapture.mockReturnValue( [ false ] );
		mockUseAuthorizationsSummary.mockReturnValue( {
			authorizationsSummary: {
				total: 0,
			},
			isLoading: false,
		} );

		await renderTransactionsPage();
		expect( screen.queryByText( 'Uncaptured' ) ).not.toBeInTheDocument();
	} );
} );
