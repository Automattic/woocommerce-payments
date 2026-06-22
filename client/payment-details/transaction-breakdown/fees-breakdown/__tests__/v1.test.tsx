/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/** Internal dependencies */
import FeesBreakdown from '../';
import { TimelineFeeBreakdown, TimelineItem } from 'wcpay/data/timeline/types';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

const baseTransactionDetails = {
	store_currency: 'USD',
	store_fee: 0,
	customer_currency: 'USD',
	customer_amount: 1000,
	customer_amount_captured: 1000,
	customer_fee: 0,
	store_amount: 1000,
	store_amount_captured: 1000,
};

const renderWithEnvelope = ( breakdown: TimelineFeeBreakdown ) => {
	const event: TimelineItem = {
		type: 'captured',
		datetime: 1713100800,
		fee_breakdown_v1: breakdown,
		transaction_details: baseTransactionDetails,
	};
	return render( <FeesBreakdown event={ event } /> );
};

describe( 'FeesBreakdown (envelope v1)', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: { country: 'US' },
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

	it( 'renders a base fee row from the envelope', () => {
		renderWithEnvelope( {
			rows: [
				{
					key: 'base',
					kind: 'fee',
					label: null,
					amount: 62,
					currency: 'USD',
					rate: {
						percentage: 0.029,
						fixed: 30,
						fixed_currency: 'USD',
					},
					meta: null,
				},
			],
			totals: {
				fee: { amount: 62, currency: 'USD' },
				tax: { amount: 0, currency: 'USD' },
				net: { amount: 938, currency: 'USD' },
				capture_net: { amount: 938, currency: 'USD' },
				gross: { amount: 1000, currency: 'USD' },
			},
			notes: [],
		} );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Total' ) ).toBeInTheDocument();
	} );

	it( 'renders only the nominal fee for Amazon Pay non-card refunded', () => {
		// Server absorbs Stripe passthrough and our refund silently: the
		// envelope arrives with a single nominal base row matching our
		// fee schedule. Merchant never sees "Stripe processing fee" or
		// "Fee adjustment" anywhere.
		renderWithEnvelope( {
			rows: [
				{
					key: 'base',
					kind: 'fee',
					label: null,
					amount: 62,
					currency: 'USD',
					rate: {
						percentage: 0.029,
						fixed: 30,
						fixed_currency: 'USD',
					},
					meta: null,
				},
			],
			totals: {
				fee: { amount: 62, currency: 'USD' },
				tax: { amount: 0, currency: 'USD' },
				net: { amount: 938, currency: 'USD' },
				capture_net: { amount: 938, currency: 'USD' },
				gross: { amount: 1000, currency: 'USD' },
			},
			notes: [],
		} );

		expect( screen.getByText( 'Base fee' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( /Stripe processing fee/i )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /Fee adjustment/i )
		).not.toBeInTheDocument();
		expect( screen.queryByText( /refund/i ) ).not.toBeInTheDocument();
	} );

	it( 'suppresses internal-only notes so they never reach merchants', () => {
		renderWithEnvelope( {
			rows: [
				{
					key: 'transaction_fee',
					kind: 'fee',
					label: null,
					amount: 150,
					currency: 'USD',
					rate: null,
					meta: null,
				},
			],
			totals: {
				fee: { amount: 150, currency: 'USD' },
				tax: { amount: 0, currency: 'USD' },
				net: { amount: 850, currency: 'USD' },
				capture_net: { amount: 850, currency: 'USD' },
				gross: { amount: 1000, currency: 'USD' },
			},
			notes: [
				{
					code: 'application_fee_refunded',
					severity: 'info',
					meta: {
						refunded_amount: 30,
						refunded_currency: 'USD',
						reason: 'amazon_pay_non_card_double_fee',
					},
				},
			],
		} );

		// Unknown / internal-only codes must not leak as raw strings.
		expect(
			screen.queryByText( 'application_fee_refunded' )
		).not.toBeInTheDocument();
	} );

	it( 'prefers the server-provided label when supplied', () => {
		renderWithEnvelope( {
			rows: [
				{
					key: 'some.unknown.key',
					kind: 'fee',
					label: 'Custom label',
					amount: 50,
					currency: 'USD',
					rate: null,
					meta: null,
				},
			],
			totals: {
				fee: { amount: 50, currency: 'USD' },
				tax: { amount: 0, currency: 'USD' },
				net: { amount: 950, currency: 'USD' },
				capture_net: { amount: 950, currency: 'USD' },
				gross: { amount: 1000, currency: 'USD' },
			},
			notes: [],
		} );

		expect( screen.getByText( 'Custom label' ) ).toBeInTheDocument();
	} );
} );
