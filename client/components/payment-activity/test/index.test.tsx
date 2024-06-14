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
		afterEach( () => {
			Date.now = () => new Date().getTime();
		} );

		const mockDateNowTo = ( date: string ) => {
			Date.now = jest.fn( () =>
				moment.tz( new Date( date ).getTime(), 'UTC' ).valueOf()
			);
		};
		const dataSet = [
			{
				// Ordinary case or Happy Path
				dateNow: '2024-06-10T16:19:29',
				expected: {
					today: 'June 10, 2024',
					last7Days: 'June 3 - June 9, 2024',
					last4Weeks: 'May 13 - June 9, 2024',
					last3Months: 'March 10 - June 9, 2024',
					last12Months: 'June 10, 2023 - June 9, 2024',
					monthToDate: 'June 1 - June 10, 2024',
					quarterToDate: 'April 1 - June 10, 2024',
					yearToDate: 'January 1 - June 10, 2024',
					allTime: 'January 1, 2022 - June 10, 2024',
				},
			},
			{
				// Start of the year
				dateNow: '2024-01-01T00:00:00',
				expected: {
					today: 'January 1, 2024',
					last7Days: 'December 25 - December 31, 2023',
					last4Weeks: 'December 4 - December 31, 2023',
					last3Months: 'October 1 - December 31, 2023',
					last12Months: 'January 1 - December 31, 2023',
					monthToDate: 'January 1, 2024',
					quarterToDate: 'January 1, 2024',
					yearToDate: 'January 1, 2024',
					allTime: 'January 1, 2022 - January 1, 2024',
				},
			},
			{
				// Leap year
				dateNow: '2024-02-29T00:00:00',
				expected: {
					today: 'February 29, 2024',
					last7Days: 'February 22 - February 28, 2024',
					last4Weeks: 'February 1 - February 28, 2024',
					last3Months: 'November 29, 2023 - February 28, 2024',
					last12Months: 'February 28, 2023 - February 28, 2024',
					monthToDate: 'February 1 - February 29, 2024',
					quarterToDate: 'January 1 - February 29, 2024',
					yearToDate: 'January 1 - February 29, 2024',
					allTime: 'January 1, 2022 - February 29, 2024',
				},
			},
		];

		it.each( dataSet )(
			'should render the correct date ranges',
			( { dateNow, expected } ) => {
				mockDateNowTo( dateNow );

				const { getByRole } = render( <PaymentActivity /> );

				const dateSelectorButton = getByRole( 'button', {
					name: 'Period',
				} );
				fireEvent.click( dateSelectorButton );

				expect(
					getByRole( 'option', { name: `Today ${ expected.today }` } )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Last 7 days ${ expected.last7Days }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Last 4 weeks ${ expected.last4Weeks }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Last 3 months ${ expected.last3Months }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Last 12 months ${ expected.last12Months }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Month to date ${ expected.monthToDate }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Quarter to date ${ expected.quarterToDate }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `Year to date ${ expected.yearToDate }`,
					} )
				).toBeInTheDocument();
				expect(
					getByRole( 'option', {
						name: `All time ${ expected.allTime }`,
					} )
				).toBeInTheDocument();
			}
		);
	} );
} );
