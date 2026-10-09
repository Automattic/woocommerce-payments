/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountFees from '..';

describe( 'AccountFees', () => {
	const renderAccountFees = ( accountFees ) => {
		return render( <AccountFees accountFees={ accountFees } /> );
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
				FR: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'right_space',
					thousandSeparator: ' ',
					decimalSeparator: ',',
					precision: 2,
				},
				GB: {
					code: 'GBP',
					symbol: '£',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
			dateFormat: 'F j, Y',
		};

		global.wooPaymentsPaymentMethodsConfig = {
			giropay: {
				title: 'giropay',
			},
			sofort: {
				title: 'Sofort',
			},
		};
	} );

	test( 'renders normal base fee', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
							percentage_rate: 0.029,
							fixed_rate: 30,
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent( 'Card transactions' );
		expect( accountFees ).toHaveTextContent(
			'2.9% + $0.30 per transaction 2.9% + $0.30 per transaction'
		);
	} );

	test( 'renders non-USD base fee', () => {
		global.wcpaySettings.connect.country = 'FR';

		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 25,
						currency: 'eur',
					},
					discount: [
						{
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
							percentage_rate: 0.029,
							fixed_rate: 30,
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent( 'Euro (EUR)' );
		expect( accountFees ).toHaveTextContent(
			'2.9% + 0,25 € per transaction 2.9% + 0,30 € per transaction'
		);
	} );

	test( 'renders discounted base fee', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							percentage_rate: 0.02,
							fixed_rate: 20,
							volume_allowance: 100000000,
							current_volume: 1234556,
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent( '2% + $0.20 per transaction' );
		expect( accountFees ).toHaveTextContent( '$12,345.56' );
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires after the first $1,000,000.00 of total payment volume.'
		);
	} );

	test( 'renders discounted non-USD base fee', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.014,
						fixed_rate: 20,
						currency: 'gbp',
					},
					discount: [
						{
							percentage_rate: 0.007,
							fixed_rate: 10,
							volume_allowance: 100000000,
							current_volume: 1234556,
							volume_currency: 'gbp',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'0.7% + £0.10 per transaction'
		);
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires after the first £1,000,000.00 of total payment volume.'
		);
	} );

	test( 'renders discounted fee with USD volume currency and non-USD base fee', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.014,
						fixed_rate: 20,
						currency: 'gbp',
					},
					discount: [
						{
							percentage_rate: 0.007,
							fixed_rate: 10,
							volume_allowance: 100000000,
							current_volume: 1234556,
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'0.7% + £0.10 per transaction'
		);
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires after the first $1,000,000.00 of total payment volume.'
		);
	} );

	test( 'renders discounted fee without volume limit', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							discount: 0.3,
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'2.03% + $0.21 per transaction (30% discount)'
		);
		expect( accountFees ).not.toHaveTextContent( 'expires' );
	} );

	test( 'renders discounted fee with volume limit', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							discount: 0.3,
							volume_allowance: 2500000,
							current_volume: 1234556,
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'2.03% + $0.21 per transaction (30% discount)'
		);
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires after the first $25,000.00 of total payment volume.'
		);
	} );

	test( 'renders discounted fee with end date', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							discount: 0.3,
							end_time: '2025-03-31 12:00:00',
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'2.03% + $0.21 per transaction (30% discount)'
		);
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires on March 31, 2025.'
		);
		expect( accountFees ).not.toHaveTextContent( 'total payment volume' );
	} );

	test( 'renders discounted fee with volume limit and end date', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							discount: 0.3,
							volume_allowance: 2500000,
							current_volume: 1234556,
							end_time: '2025-03-31 12:00:00',
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'Discounted base fee expires after the first $25,000.00 of total payment volume or on March 31, 2025.'
		);
	} );

	test( 'renders first discounted fee ignoring the rest', () => {
		// This is a limitation of the current UI, not necessarily a desired behavior.
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							discount: 0.2,
							volume_currency: 'usd',
						},
						{
							discount: 0.3,
							volume_allowance: 2500000,
							current_volume: 1234556,
							volume_currency: 'usd',
						},
					],
				},
			},
		] );
		expect( accountFees ).toHaveTextContent(
			'2.32% + $0.24 per transaction (20% discount)'
		);
	} );

	test( 'renders without any discounts', () => {
		const { container: accountFees } = renderAccountFees( [] );

		expect( accountFees ).toBeEmptyDOMElement();
	} );

	test( 'renders discounts multiple payment methods', () => {
		const { container: accountFees } = renderAccountFees( [
			{
				payment_method: 'card',
				fee: {
					base: {
						percentage_rate: 0.029,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
							percentage_rate: 0.014,
							fixed_rate: 25,
						},
					],
				},
			},
			{
				payment_method: 'card_present',
				fee: {
					base: {
						percentage_rate: 0.027,
						fixed_rate: 5,
						currency: 'usd',
					},
					discount: [],
				},
			},
			{
				payment_method: 'giropay',
				fee: {
					base: {
						percentage_rate: 0.014,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
							percentage_rate: 0.014,
							fixed_rate: 25,
						},
					],
				},
			},
			{
				payment_method: 'sofort',
				fee: {
					base: {
						percentage_rate: 0.014,
						fixed_rate: 30,
						currency: 'usd',
					},
					discount: [
						{
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
							percentage_rate: 0.014,
							fixed_rate: 25,
						},
					],
				},
			},
		] );

		expect( accountFees ).toHaveTextContent( 'Card transactions' );
		expect( accountFees ).toHaveTextContent( 'giropay transactions' );
		expect( accountFees ).toHaveTextContent( 'Sofort transactions' );
		expect( accountFees ).not.toHaveTextContent( 'In-person transactions' );
	} );
} );
