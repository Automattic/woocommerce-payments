/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import PaymentDetailsTimeline from '../';
import { useTimeline } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useTimeline: jest.fn(),
} ) );

describe( 'PaymentDetailsTimeline', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			featureFlags: {
				paymentTimeline: true,
			},
			zeroDecimalCurrencies: [],
		};
	} );

	afterEach( () => {
		delete global.wcpaySettings;
	} );

	test( 'renders loading', () => {
		useTimeline.mockReturnValue( {
			timeline: [],
			timelineError: null,
			isLoading: true,
		} );

		const { container } = render(
			<PaymentDetailsTimeline chargeId={ 'ch_test' } />
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders error', () => {
		useTimeline.mockReturnValue( {
			timeline: [],
			timelineError: new Error( 'Test error' ),
			isLoading: false,
		} );

		const { container } = render(
			<PaymentDetailsTimeline chargeId={ 'ch_test' } />
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly (with a mocked Timeline component)', () => {
		// Mock all the possible events.
		useTimeline.mockReturnValue( {
			timeline: [
				{
					amount: 7900,
					currency: 'USD',
					datetime: 1585589596,
					type: 'authorized',
				},
				{
					amount: 5900,
					currency: 'USD',
					datetime: 1585652279,
					type: 'authorization_voided',
				},
				{
					amount: 8600,
					currency: 'USD',
					datetime: 1585691920,
					type: 'authorization_expired',
				},
				{
					amount: 7700,
					currency: 'USD',
					datetime: 1585712113,
					reason: 'card_declined',
					type: 'failed',
				},
				{
					amount: 6300,
					currency: 'USD',
					datetime: 1585751874,
					deposit: {
						arrival_date: 1585838274,
						id: 'dummy_po_5eaada696b281',
					},
					fee: 350,
					type: 'captured',
				},
				{
					amount: 9500,
					currency: 'USD',
					datetime: 1585793174,
					deposit: null,
					dispute_id: 'some_id',
					evidence_due_by: 1585879574,
					fee: 1500,
					reason: 'fraudulent',
					type: 'dispute_needs_response',
				},
				{
					datetime: 1585859207,
					type: 'dispute_in_review',
					user_id: 1,
				},
				{
					amount_refunded: 5000,
					currency: 'USD',
					datetime: 1585940281,
					deposit: null,
					type: 'partial_refund',
				},
				{
					amount_refunded: 10000,
					currency: 'USD',
					datetime: 1586008266,
					deposit: null,
					type: 'full_refund',
				},
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1586017250,
					deposit: {
						arrival_date: 1586103650,
						id: 'dummy_po_5eaada696b2d3',
					},
					fee: 1500,
					type: 'dispute_won',
				},
				{
					amount: 10000,
					currency: 'USD',
					datetime: 1586055370,
					deposit: {
						arrival_date: 1586141770,
						id: 'dummy_po_5eaada696b2ef',
					},
					fee: 1500,
					type: 'dispute_lost',
				},
			],
			timelineError: null,
			isLoading: false,
		} );

		const { container } = render(
			<PaymentDetailsTimeline chargeId={ 'ch_test' } />
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders subscription fee correctly', () => {
		// Mock all the possible events.
		useTimeline.mockReturnValue( {
			timeline: [
				{
					type: 'captured',
					amount: 100,
					fee: 34,
					fee_rates: {
						percentage: 0.039,
						fixed: 30,
						fixed_currency: 'USD',
						history: [
							{
								type: 'base',
								percentage_rate: 0.029,
								fixed_rate: 30,
								currency: 'usd',
							},
							{
								type: 'additional',
								additional_type: 'wcpay-subscription',
								percentage_rate: 0.01,
								fixed_rate: 0,
								currency: 'usd',
							},
						],
					},
					currency: 'USD',
					datetime: 1633375102,
					deposit: null,
					transaction_id: 'txn_3Jgwg6R3oniasQM30OzCiu0j',
					transaction_details: {
						customer_currency: 'USD',
						customer_amount: 100,
						customer_fee: 34,
						store_currency: 'USD',
						store_amount: 100,
						store_fee: 34,
					},
				},
				{
					type: 'authorized',
					datetime: 1633375102,
					amount: 100,
					currency: 'USD',
				},
			],
			timelineError: null,
			isLoading: false,
		} );

		const { container } = render(
			<PaymentDetailsTimeline chargeId={ 'ch_test' } />
		);

		expect( container ).toMatchSnapshot();
	} );
} );
