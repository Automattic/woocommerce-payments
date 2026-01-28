/** @format */
/**
 * External dependencies
 */
import { render, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useUserPreferences } from '@woocommerce/data';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisputesList from '..';
import { useDisputes, useDisputesSummary, useSettings } from 'data';
import React, { act } from 'react';
import {
	CachedDispute,
	DisputeReason,
	DisputeStatus,
} from 'wcpay/types/disputes';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

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

jest.mock( 'data/index', () => ( {
	useDisputes: jest.fn(),
	useDisputesSummary: jest.fn(),
	useSettings: jest.fn(),
	usePmPromotions: jest
		.fn()
		.mockReturnValue( { pmPromotions: [], isLoading: false } ),
	usePmPromotionActions: jest.fn().mockReturnValue( {
		activatePmPromotion: jest.fn(),
		dismissPmPromotion: jest.fn(),
	} ),
} ) );

jest.mock( '@woocommerce/data', () => {
	const actualModule = jest.requireActual( '@woocommerce/data' );

	return {
		...actualModule,
		useUserPreferences: jest.fn(),
	};
} );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const mockUseDisputes = useDisputes as jest.MockedFunction<
	typeof useDisputes
>;

const mockUseDisputesSummary = useDisputesSummary as jest.MockedFunction<
	typeof useDisputesSummary
>;

const mockUseSettings = useSettings as jest.MockedFunction<
	typeof useSettings
>;

const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currentUserEmail: string;
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
		dateFormat?: string;
		timeFormat?: string;
	};
};

const mockDisputes = [
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_asdfghjkl',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'fraudulent' as DisputeReason,
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response' as DisputeStatus,
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-10 02:46:00',
		order: {
			number: '1',
			customer_url: 'https://shop.local',
			url: 'http://test.local/order/1',
		} as any,
	} as CachedDispute,
	{
		// dispute without order or charge information
		wcpay_disputes_cache_id: 5,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_zxcvbnm',
		charge_id: 'ch_mock',
		amount: 1050,
		currency: 'usd',
		reason: 'general' as DisputeReason,
		order_number: 2,
		status: 'under_review' as DisputeStatus,
		created: '2019-10-30 09:14:33',
		due_by: '2019-11-06 23:00:59',
	} as CachedDispute,
	{
		wcpay_disputes_cache_id: 6,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_rstyuoi',
		charge_id: 'ch_mock',
		amount: 2000,
		currency: 'usd',
		reason: 'general' as DisputeReason,
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response' as DisputeStatus,
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-08 02:46:00',
		order: {
			number: '3',
			url: 'http://test.local/order/3',
		} as any,
	} as CachedDispute,
];

describe( 'Disputes list', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () =>
			new Date( '2019-11-07T12:33:37.000Z' ).getTime()
		);

		mockUseSettings.mockReturnValue( {
			isLoading: false,
			isSaving: false,
			saveSettings: () => null,
			isDirty: false,
		} );

		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: jest.fn(),
			wc_payments_disputes_hidden_columns: '',
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
			dateFormat: 'Y-m-d',
			timeFormat: 'g:iA',
		};
	} );

	afterEach( () => {
		// roll it back
		Date.now = () => new Date().getTime();
	} );

	test( 'renders correctly', () => {
		mockUseDisputes.mockReturnValue( {
			isLoading: false,
			disputes: mockDisputes,
		} );

		mockUseDisputesSummary.mockReturnValue( {
			isLoading: false,
			disputesSummary: {
				count: 25,
			},
		} );

		const { container: list } = render( <DisputesList /> );
		expect( list ).toMatchSnapshot();
	} );

	test( 'renders columns hidden as per user preferences', () => {
		mockUseDisputes.mockReturnValue( {
			isLoading: false,
			disputes: mockDisputes,
		} );

		mockUseDisputesSummary.mockReturnValue( {
			isLoading: false,
			disputesSummary: {
				count: 25,
			},
		} );

		mockUseUserPreferences.mockReturnValue( {
			wc_payments_disputes_hidden_columns: [ 'customerEmail' ],
		} as any );

		const { getByRole, queryByRole } = render( <DisputesList /> );

		// Email column should not be visible, as it is hidden in user preferences.
		expect(
			queryByRole( 'columnheader', {
				name: /Email/i,
			} )
		).not.toBeInTheDocument();

		// Country column should be visible, as it is not hidden in user preferences.
		expect(
			getByRole( 'columnheader', {
				name: /Country/i,
			} )
		).toBeInTheDocument();
	} );

	describe( 'Download button', () => {
		test( 'renders when there are one or more disputes', () => {
			mockUseDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Export' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no disputes', () => {
			mockUseDisputes.mockReturnValue( {
				disputes: [],
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Export' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			jest.restoreAllMocks();
			mockUseDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );

			mockUseDisputesSummary.mockReturnValue( {
				isLoading: false,
				disputesSummary: {
					count: 3,
				},
			} );
		} );

		test( 'should fetch export after confirmation when download button is selected for unfiltered exports larger than 1000.', async () => {
			window.confirm = jest.fn( () => true );
			mockUseDisputesSummary.mockReturnValue( {
				disputesSummary: {
					count: 1100,
				},
				isLoading: false,
			} );

			const { getByRole } = render( <DisputesList /> );

			await act( async () => {
				await userEvent.click(
					getByRole( 'button', { name: 'Export' } )
				);
			} );

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 1100 disputes. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () => {
				expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
				expect( mockApiFetch ).toHaveBeenCalledWith( {
					method: 'POST',
					path:
						'/wc/v3/payments/disputes/download?user_email=mock%40example.com&locale=en_US',
				} );
			} );
		} );
	} );
} );
