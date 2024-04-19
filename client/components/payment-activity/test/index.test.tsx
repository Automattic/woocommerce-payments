/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

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
		total_payment_volume: 123456,
		charges: 9876,
		fees: 1234,
		disputes: 5555,
		refunds: 4444,
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
			},
			accountDefaultCurrency: 'USD',
			zeroDecimalCurrencies: [],
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
		const { container, getByText } = render( <PaymentActivity /> );

		// Check survey is rendered.
		getByText( 'Are those metrics helpful?' );

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
			queryByText( 'Are those metrics helpful?' )
		).not.toBeInTheDocument();
	} );
} );
