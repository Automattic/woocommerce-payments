/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentTransactionBreakdown from '../';
import { useTimeline } from 'wcpay/data';
import { useTransactionAmounts } from '../hooks';
import { TimelineItem } from 'wcpay/data/timeline/types';
import { TransactionDetails } from '../types';

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn().mockImplementation( ( str ) => str ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useTimeline: jest.fn(),
} ) );

jest.mock( '../hooks', () => ( {
	useTransactionAmounts: jest.fn(),
} ) );

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'PaymentTransactionBreakdown', () => {
	beforeEach( () => {
		( useTimeline as jest.Mock ).mockReset();
		( useTransactionAmounts as jest.Mock ).mockReset();
		global.wcpaySettings = {
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
			},
		};
	} );

	it( 'renders nothing when no capture event is found', () => {
		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [],
			isLoading: false,
		} );

		const { container } = render(
			<PaymentTransactionBreakdown paymentIntentId="pi_123" />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing in loading state', () => {
		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [],
			isLoading: true,
		} );

		const { container } = render(
			<PaymentTransactionBreakdown paymentIntentId="pi_123" />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders empty state on error', () => {
		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [],
			timelineError: new Error( 'Failed to load' ),
			isLoading: false,
		} );

		const { container } = render(
			<PaymentTransactionBreakdown paymentIntentId="pi_123" />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders transaction breakdown with single fee without history', () => {
		const mockTransactionDetails: TransactionDetails = {
			store_amount: 10000,
			store_amount_captured: 10000,
			store_currency: 'USD',
			customer_amount: 10000,
			customer_currency: 'USD',
			customer_amount_captured: 10000,
			customer_fee: 320,
			store_fee: 320,
		};

		const mockCaptureEvent: TimelineItem = {
			type: 'captured',
			datetime: 1717334400,
			transaction_details: mockTransactionDetails,
			fee_rates: {
				percentage: 0.029,
				fixed: 30,
				fixed_currency: 'USD',
			},
		};

		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [ mockCaptureEvent ],
			isLoading: false,
		} );

		( useTransactionAmounts as jest.Mock ).mockReturnValue( {
			formattedAmount: '$100.00 USD',
			formattedStoreAmount: '$100.00 USD',
			formattedCustomerAmount: '$100.00 USD',
			isMultiCurrency: false,
		} );

		render( <PaymentTransactionBreakdown paymentIntentId="pi_123" /> );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector: '.wcpay-transaction-breakdown__base_fee_info div',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Total transaction fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /- \$3.20 USD$/, {
				selector: '.wcpay-transaction-breakdown__total_fee_info div',
			} )
		).toBeInTheDocument();
	} );

	it( 'renders transaction breakdown with multiple fees in history', () => {
		const mockTransactionDetails: TransactionDetails = {
			store_amount: 10000,
			store_amount_captured: 10000,
			store_currency: 'USD',
			customer_amount: 10000,
			customer_currency: 'USD',
			customer_amount_captured: 10000,
			customer_fee: 520,
			store_fee: 520,
		};

		const mockCaptureEvent: TimelineItem = {
			type: 'captured',
			datetime: 1717334400,
			transaction_details: mockTransactionDetails,
			fee_rates: {
				percentage: 0.049,
				fixed: 30,
				fixed_currency: 'USD',
				history: [
					{
						type: 'base',
						fee_id: 'base',
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'USD',
					},
					{
						type: 'additional',
						additional_type: 'international',
						fee_id: 'international',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
					},
					{
						type: 'additional',
						additional_type: 'fx',
						fee_id: 'fx',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
					},
				],
			},
		};

		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [ mockCaptureEvent ],
			isLoading: false,
		} );

		( useTransactionAmounts as jest.Mock ).mockReturnValue( {
			formattedAmount: '$100.00 USD',
			formattedStoreAmount: '$100.00 USD',
			formattedCustomerAmount: '$100.00 USD',
			isMultiCurrency: false,
		} );

		render( <PaymentTransactionBreakdown paymentIntentId="pi_123" /> );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector: '.wcpay-transaction-breakdown__base_fee_info div',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'International card fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '1%', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info div',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Currency conversion fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '1%', {
				selector:
					'.wcpay-transaction-breakdown__additional_fx_fee_info div',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Total transaction fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /- \$5.20 USD$/, {
				selector: '.wcpay-transaction-breakdown__total_fee_info div',
			} )
		).toBeInTheDocument();
	} );

	it( 'renders transaction breakdown with conversion rate for multi-currency payment', () => {
		const mockTransactionDetails: TransactionDetails = {
			store_amount: 8500,
			store_amount_captured: 8500,
			store_currency: 'EUR',
			customer_amount: 10000,
			customer_currency: 'USD',
			customer_amount_captured: 10000,
			customer_fee: 320,
			store_fee: 320,
		};

		const mockCaptureEvent: TimelineItem = {
			type: 'captured',
			datetime: 1717334400,
			transaction_details: mockTransactionDetails,
			fee_rates: {
				percentage: 0.029,
				fixed: 30,
				fixed_currency: 'USD',
				fee_exchange_rate: {
					from_currency: 'USD',
					to_currency: 'EUR',
					from_amount: 10000,
					to_amount: 8500,
					rate: 0.85,
				},
			},
		};

		( useTimeline as jest.Mock ).mockReturnValue( {
			timeline: [ mockCaptureEvent ],
			isLoading: false,
		} );

		( useTransactionAmounts as jest.Mock ).mockReturnValue( {
			formattedAmount: '$100.00 USD → €85.00 EUR',
			formattedStoreAmount: '€85.00 EUR',
			formattedCustomerAmount: '$100.00 USD',
			isMultiCurrency: true,
		} );

		render( <PaymentTransactionBreakdown paymentIntentId="pi_123" /> );

		expect(
			screen.getByText( 'Transaction breakdown' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Authorized payment' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '$100.00 USD → €85.00 EUR' )
		).toBeInTheDocument();

		const conversionRateText = screen.getByText( 'USD', {
			selector: 'div.wcpay-transaction-breakdown__conversion_rate',
			exact: false,
		} );
		expect( conversionRateText ).toHaveTextContent(
			'@ 1 USD → 1.176471 EUR'
		);
	} );
} );
