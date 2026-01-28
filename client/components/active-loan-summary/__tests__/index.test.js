/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ActiveLoanSummary from '..';
import { useActiveLoanSummary } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useActiveLoanSummary: jest.fn(),
} ) );

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, 'UTC' ); // Ensure UTC is used
	} ),
} ) );

describe( 'Active loan summary', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			accountLoans: {
				loans: [ 'flxln_123456|active' ],
			},
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
			dateFormat: 'M j, Y',
		};
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when loading', () => {
		useActiveLoanSummary.mockReturnValue( {
			summary: null,
			isLoading: true,
		} );
		const { container } = render( <ActiveLoanSummary /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly', () => {
		useActiveLoanSummary.mockReturnValue( {
			summary: {
				details: {
					advance_amount: 100000,
					advance_paid_out_at: 1643889167,
					currency: 'usd',
					current_repayment_interval: {
						due_at: 1644889167,
						paid_amount: 123,
						remaining_amount: 2345,
					},
					fee_amount: 15000,
					paid_amount: 1234,
					remaining_amount: 9876,
					repayments_begin_at: 1643999167,
					withhold_rate: 10,
				},
			},
			isLoading: false,
		} );
		const { container } = render( <ActiveLoanSummary /> );
		expect( container ).toMatchSnapshot();
	} );
} );
