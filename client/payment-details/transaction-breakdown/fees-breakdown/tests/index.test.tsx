/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/** Internal dependencies */
import FeesBreakdown from '../';
import { TimelineItem } from 'wcpay/data/timeline/types';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'FeesBreakdown', () => {
	const baseEvent: TimelineItem = {
		type: 'capture',
		datetime: 1713100800,
		fee_rates: {
			percentage: 0.029,
			fixed: 30,
			fixed_currency: 'USD',
			fee_exchange_rate: {
				from_currency: 'USD',
				to_currency: 'USD',
				from_amount: 100,
				to_amount: 100,
				rate: 1,
			},
		},
		transaction_details: {
			store_currency: 'USD',
			store_fee: 10,
			customer_currency: 'USD',
			customer_amount: 100,
			customer_amount_captured: 100,
			customer_fee: 10,
			store_amount: 100,
			store_amount_captured: 100,
		},
	};

	beforeEach( () => {
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

	it( 'should render null when fee_rates or transaction_details are missing', () => {
		const { container } = render(
			<FeesBreakdown
				event={
					{
						...baseEvent,
						fee_rates: undefined,
					} as TimelineItem
				}
			/>
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'should render base fee when fee_history is undefined', () => {
		render( <FeesBreakdown event={ baseEvent } /> );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__base_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__total_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
	} );

	it( 'should render fees with discount when fee_history contains a discount', () => {
		const eventWithDiscount: TimelineItem = {
			...baseEvent,
			transaction_details: {
				store_currency: 'USD',
				store_fee: 0,
				customer_currency: 'USD',
				customer_amount: 100,
				customer_amount_captured: 100,
				customer_fee: 0,
				store_amount: 100,
				store_amount_captured: 100,
			},
			fee_rates: {
				...baseEvent.fee_rates,
				percentage: 0.004,
				fixed: 5,
				fixed_currency: 'USD',
				history: [
					{
						type: 'base',
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'USD',
						fee_id: 'base',
					},
					{
						type: 'additional',
						additional_type: 'international',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
						fee_id: 'additional',
					},
					{
						type: 'discount',
						percentage_rate: -0.035,
						fixed_rate: -25,
						currency: 'USD',
						fee_id: 'discount',
					},
				],
			},
		};

		render( <FeesBreakdown event={ eventWithDiscount } /> );

		expect(
			screen.getByText( 'Base fee (discounted)' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '0% + $0.05 USD', {
				selector:
					'.wcpay-transaction-breakdown__base_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'International payment fee (discounted)', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info  .wcpay-transaction-breakdown__fee_name',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( '0.4%', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '0.4% + $0.05 USD', {
				selector:
					'.wcpay-transaction-breakdown__total_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
	} );

	it( 'should render fees without discount when fee_history has no discount', () => {
		const eventWithoutDiscount: TimelineItem = {
			...baseEvent,
			fee_rates: {
				...baseEvent.fee_rates,
				percentage: 0.039,
				fixed: 30,
				fixed_currency: 'USD',
				history: [
					{
						type: 'base',
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'USD',
						fee_id: 'base',
					},
					{
						type: 'additional',
						additional_type: 'international',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
						fee_id: 'additional',
					},
				],
			},
		};

		render( <FeesBreakdown event={ eventWithoutDiscount } /> );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'International payment fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__base_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( '1%', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '3.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__total_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
	} );

	it( 'should render fee line with 0% when fee is fully discounted', () => {
		const eventWithoutDiscount: TimelineItem = {
			...baseEvent,
			transaction_details: {
				store_currency: 'USD',
				store_fee: 0,
				customer_currency: 'USD',
				customer_amount: 100,
				customer_amount_captured: 100,
				customer_fee: 0,
				store_amount: 100,
				store_amount_captured: 100,
			},
			fee_rates: {
				...baseEvent.fee_rates,
				percentage: 0,
				fixed: 0,
				fixed_currency: 'USD',
				history: [
					{
						type: 'base',
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'USD',
						fee_id: 'base',
					},
					{
						type: 'additional',
						additional_type: 'international',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
						fee_id: 'additional',
					},
					{
						type: 'discount',
						percentage_rate: -0.039,
						fixed_rate: -30,
						currency: 'USD',
						fee_id: 'discount',
					},
				],
			},
		};

		render( <FeesBreakdown event={ eventWithoutDiscount } /> );

		expect(
			screen.getByText( 'Base fee (discounted)' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'International payment fee (discounted)' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '0% + $0.00 USD', {
				selector:
					'.wcpay-transaction-breakdown__base_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( '0%', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '0% + $0.00 USD', {
				selector:
					'.wcpay-transaction-breakdown__total_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
	} );

	it( 'should render fees without discount when fee_history has no discount', () => {
		const eventWithoutDiscount: TimelineItem = {
			...baseEvent,
			fee_rates: {
				...baseEvent.fee_rates,
				percentage: 0.039,
				fixed: 30,
				fixed_currency: 'USD',
				history: [
					{
						type: 'base',
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'USD',
						fee_id: 'base',
					},
					{
						type: 'additional',
						additional_type: 'international',
						percentage_rate: 0.01,
						fixed_rate: 0,
						currency: 'USD',
						fee_id: 'additional',
					},
				],
			},
		};

		render( <FeesBreakdown event={ eventWithoutDiscount } /> );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'International payment fee' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '2.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__base_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( '1%', {
				selector:
					'.wcpay-transaction-breakdown__additional_international_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '3.9% + $0.30 USD', {
				selector:
					'.wcpay-transaction-breakdown__total_fee_info .wcpay-transaction-breakdown__fee_rate',
			} )
		).toBeInTheDocument();
	} );
} );
