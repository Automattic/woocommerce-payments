/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { usePaymentActivityData } from 'wcpay/data';
import PaymentActivity from '..';

jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	combineReducers: jest.fn(),
	select: jest.fn(),
	useSelect: jest.fn(),
	useDispatch: jest.fn( () => ( {
		createNotice: jest.fn(),
		createErrorNotice: jest.fn(),
	} ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	usePaymentActivityData: jest.fn(),
} ) );

const mockUsePaymentActivityData = usePaymentActivityData as jest.MockedFunction<
	typeof usePaymentActivityData
>;

mockUsePaymentActivityData.mockReturnValue( {
	paymentActivityData: {
		currency: 'eur',
		total_payment_volume: 123456,
		charges: 9876,
		fees: 1234,
		disputes: 5555,
		refunds: 4444,
		date_start: '2024-01-01',
		date_end: '2024-01-31',
		timezone: 'UTC',
		interval: 'daily',
	},
	isLoading: false,
} );

declare const global: {
	wcpaySettings: {
		lifetimeTPV: number;
		isOverviewSurveySubmitted?: boolean;
		accountStatus: {
			deposits: {
				restrictions: string;
				completed_waiting_period: boolean;
				minimum_scheduled_deposit_amounts: {
					[ currencyCode: string ]: number;
				};
			};
			created: string;
		};
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'PaymentActivity component', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			lifetimeTPV: 1000,
			accountStatus: {
				deposits: {
					restrictions: 'deposits_unrestricted',
					completed_waiting_period: true,
					minimum_scheduled_deposit_amounts: {
						eur: 500,
						usd: 500,
					},
				},
				created: '2022-01-01T00:00:00Z',
			},
			accountDefaultCurrency: 'eur',
			zeroDecimalCurrencies: [],
			connect: {
				country: 'DE',
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
				EU: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'left',
					thousandSeparator: '.',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
		Date.now = jest.fn( () =>
			new Date( '2024-04-08T12:33:37.000Z' ).getTime()
		);
	} );

	afterEach( () => {
		Date.now = () => new Date().getTime();
	} );

	it( 'should render', () => {
		const { container, getByText, getByLabelText, getAllByText } = render(
			<PaymentActivity />
		);

		// Check survey is rendered.
		getByText( 'Are these metrics helpful?' );

		// Check correct currency/value is displayed.
		const tpvElement = getByLabelText( 'Total payment volume' );
		expect( tpvElement ).toHaveTextContent( '€1.234,56' );

		// Check the "View report" link is rendered with the correct currency query param.
		const viewReportLinks = getAllByText( 'View report' );
		viewReportLinks.forEach( ( link ) => {
			expect( link ).toHaveAttribute(
				'href',
				expect.stringContaining( 'store_currency_is=eur' )
			);
		} );

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render an empty state', () => {
		global.wcpaySettings.lifetimeTPV = 0;

		const { container, getByText } = render( <PaymentActivity /> );

		expect( getByText( 'No payments…yet!' ) ).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	it( 'should not render survey if survey is already submitted', () => {
		global.wcpaySettings.isOverviewSurveySubmitted = true;

		const { queryByText } = render( <PaymentActivity /> );

		expect(
			queryByText( 'Are these metrics helpful?' )
		).not.toBeInTheDocument();
	} );

	describe( 'Date selector renders correct ranges', () => {
		/* const dataSet = [
			{
				rightNow: '2024-06-10T16:19:29',
				preset: 'today',
				start: '2024-06-12T00:00:00',
				end: '2024-06-12T23:59:59',
			},
		]; */
		it( 'should render the correct date ranges', () => {
			Date.now = jest.fn( () =>
				moment
					.tz( new Date( '2024-06-10T16:19:29' ).getTime(), 'UTC' )
					.valueOf()
			);

			const { container, getAllByRole } = render( <PaymentActivity /> );

			const dateSelectorButton = getAllByRole( 'button' )[ 0 ];
			fireEvent.click( dateSelectorButton );
			const datePresetOptions = getAllByRole( 'option' );

			expect( datePresetOptions ).toHaveLength( 9 );
			expect( datePresetOptions[ 0 ] ).toHaveTextContent(
				'TodayJune 10, 2024'
			);
			expect( datePresetOptions[ 1 ] ).toHaveTextContent(
				'Last 7 daysJune 3 - June 9, 2024'
			);
			expect( datePresetOptions[ 2 ] ).toHaveTextContent(
				'Last 4 weeksMay 13 - June 9, 2024'
			);
			expect( datePresetOptions[ 3 ] ).toHaveTextContent(
				'Last 3 monthsMarch 10 - June 9, 2024'
			);
			expect( datePresetOptions[ 4 ] ).toHaveTextContent(
				'Last 12 monthsJune 10, 2023 - June 9, 2024'
			);
			expect( datePresetOptions[ 5 ] ).toHaveTextContent(
				'Month to dateJune 1 - June 10, 2024'
			);
			expect( datePresetOptions[ 6 ] ).toHaveTextContent(
				'Quarter to dateApril 1 - June 10, 2024'
			);
			expect( datePresetOptions[ 7 ] ).toHaveTextContent(
				'Year to dateJanuary 1 - June 10, 2024'
			);
			expect( datePresetOptions[ 8 ] ).toHaveTextContent( 'All time' );

			Date.now = () => new Date().getTime();
		} );
	} );
} );
