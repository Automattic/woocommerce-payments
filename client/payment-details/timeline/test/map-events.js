/** @format */
/**
 * Internal dependencies
 */
import mapTimelineEvents from '../map-events';

jest.mock( 'gridicons/dist/sync', () => 'SyncIcon' );
jest.mock( 'gridicons/dist/plus', () => 'PlusIcon' );
jest.mock( 'gridicons/dist/minus', () => 'MinusIcon' );
jest.mock( 'gridicons/dist/info-outline', () => 'InfoOutlineIcon' );
jest.mock( 'gridicons/dist/checkmark', () => 'CheckmarkIcon' );
jest.mock( 'gridicons/dist/cross', () => 'CrossIcon' );
jest.mock( 'gridicons/dist/notice-outline', () => 'NoticeOutlineIcon' );

describe( 'mapTimelineEvents', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			shouldUseExplicitPrice: true,
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
		};
	} );

	test( 'handles falsey values', () => {
		expect( mapTimelineEvents( null ) ).toStrictEqual( [] );
	} );

	test( 'formats authorized events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 7900,
					currency: 'USD',
					datetime: 1585589596,
					type: 'authorized',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats authorization_voided events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 5900,
					currency: 'USD',
					datetime: 1585652279,
					type: 'authorization_voided',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats authorization_expired events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 8600,
					currency: 'USD',
					datetime: 1585691920,
					type: 'authorization_expired',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats card_declined events', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: 7700,
					currency: 'USD',
					datetime: 1585712113,
					reason: 'card_declined',
					type: 'failed',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_needs_response events with no amount', () => {
		expect(
			mapTimelineEvents( [
				{
					amount: null,
					currency: null,
					datetime: 1585793174,
					deposit: null,
					dispute_id: 'some_id',
					evidence_due_by: 1585879574,
					fee: null,
					reason: 'fraudulent',
					type: 'dispute_needs_response',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_warning_closed events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585793174,
					type: 'dispute_warning_closed',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_charge_refunded events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585793174,
					type: 'dispute_charge_refunded',
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats dispute_in_review events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585859207,
					type: 'dispute_in_review',
					user_id: 1,
				},
			] )
		).toMatchSnapshot();
	} );

	test( 'formats refund_failed events', () => {
		expect(
			mapTimelineEvents( [
				{
					datetime: 1585859207,
					type: 'refund_failed',
					user_id: 1,
					acquirer_reference_number_status: 'available',
					acquirer_reference_number: '4785767637658864',
					failure_reason: 'expired_or_canceled_card',
					amount_refunded: '100',
				},
			] )
		).toMatchSnapshot();
	} );

	describe( 'single currency events', () => {
		test( 'formats captured events without fee details', () => {
			expect(
				mapTimelineEvents( [
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
				] )
			).toMatchSnapshot();
		} );

		test( 'should not render fee breakup when fee history is not present', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 6300,
						currency: 'USD',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 350,
						fee_rates: {
							percentage: 0.0195,
							fixed: 15,
							fixed_currency: 'USD',
						},
						type: 'captured',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats captured events with fee details', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 6300,
						currency: 'USD',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 350,
						fee_rates: {
							percentage: 0.0195,
							fixed: 15,
							fixed_currency: 'USD',
							history: [
								{
									type: 'base',
									percentage_rate: 0.014,
									fixed_rate: 20,
									currency: 'gbp',
								},
								{
									type: 'additional',
									additional_type: 'international',
									percentage_rate: 0.014999999999999998,
									fixed_rate: 0,
									currency: 'gbp',
								},
								{
									type: 'additional',
									additional_type: 'fx',
									percentage_rate: 0.020000000000000004,
									fixed_rate: 0,
									currency: 'gbp',
								},
								{
									type: 'discount',
									percentage_rate: -0.049,
									fixed_rate: -20,
									currency: 'gbp',
								},
							],
						},
						type: 'captured',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats captured events with just the base fee', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 6300,
						currency: 'USD',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 350,
						fee_rates: {
							percentage: 0.0195,
							fixed: 15,
							fixed_currency: 'USD',
							history: [
								{
									type: 'base',
									percentage_rate: 0.014,
									fixed_rate: 20,
									currency: 'gbp',
								},
							],
						},
						type: 'captured',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats dispute_needs_response events', () => {
			expect(
				mapTimelineEvents( [
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
				] )
			).toMatchSnapshot();
		} );

		test( 'formats partial_refund events', () => {
			expect(
				mapTimelineEvents( [
					{
						amount_refunded: 5000,
						currency: 'USD',
						datetime: 1585940281,
						deposit: null,
						type: 'partial_refund',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats full_refund events', () => {
			expect(
				mapTimelineEvents( [
					{
						amount_refunded: 10000,
						currency: 'USD',
						datetime: 1586008266,
						deposit: null,
						type: 'full_refund',
						acquirer_reference_number_status: 'available',
						acquirer_reference_number: '4785767637658864',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats dispute_won events', () => {
			expect(
				mapTimelineEvents( [
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
				] )
			).toMatchSnapshot();
		} );

		test( 'formats dispute_lost events', () => {
			expect(
				mapTimelineEvents( [
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
				] )
			).toMatchSnapshot();
		} );

		test( 'formats financing paydown events', () => {
			expect(
				mapTimelineEvents( [
					{
						type: 'financing_paydown',
						datetime: 1643717044,
						amount: -11000,
						loan_id: 'flxln_1KOKzdR4ByxURRrFX9A65q40',
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'in person payments - tap to pay', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 1980,
						currency: 'USD',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 61,
						fee_rates: {
							percentage: 0.026,
							fixed: 20,
							fixed_currency: 'USD',
							history: [
								{
									type: 'base',
									percentage_rate: 0.026,
									fixed_rate: 10,
									currency: 'usd',
								},
								{
									type: 'additional',
									additional_type: 'device',
									percentage_rate: 0,
									fixed_rate: 10,
									currency: 'usd',
								},
							],
						},
						type: 'captured',
					},
				] )
			).toMatchSnapshot();
		} );
	} );

	describe( 'Multi-Currency events', () => {
		test( 'formats captured events without fee details', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 1800,
						currency: 'EUR',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 52,
						type: 'captured',
						transaction_details: {
							customer_amount: 1800,
							customer_currency: 'EUR',
							customer_fee: 52,
							store_amount: 2159,
							store_currency: 'USD',
							store_fee: 62,
						},
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats captured events with fee details', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 1800,
						currency: 'EUR',
						datetime: 1585751874,
						deposit: {
							arrival_date: 1585838274,
							id: 'dummy_po_5eaada696b281',
						},
						fee: 52,
						fee_rates: {
							percentage: 0.029,
							fixed: 30,
							fixed_currency: 'USD',
						},
						type: 'captured',
						transaction_details: {
							customer_amount: 1800,
							customer_currency: 'EUR',
							customer_fee: 52,
							store_amount: 2159,
							store_currency: 'USD',
							store_fee: 62,
						},
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats dispute_needs_response events', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: -2160,
						currency: 'USD',
						datetime: 1585793174,
						deposit: null,
						dispute_id: 'some_id',
						evidence_due_by: 1585879574,
						fee: 1500,
						reason: 'fraudulent',
						type: 'dispute_needs_response',
						transaction_details: {
							customer_amount: 1800,
							customer_currency: 'EUR',
							customer_fee: null,
							store_amount: -2160,
							store_currency: 'USD',
							store_fee: 1500,
						},
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats partial_refund events', () => {
			global.wcpaySettings.connect.country = 'FR';

			expect(
				mapTimelineEvents( [
					{
						amount_refunded: 500,
						currency: 'EUR',
						datetime: 1585940281,
						deposit: null,
						type: 'partial_refund',
						acquirer_reference_number_status: 'available',
						acquirer_reference_number: '4785767637658864',
						transaction_details: {
							customer_amount: 500,
							customer_currency: 'EUR',
							customer_fee: 0,
							store_amount: 600,
							store_currency: 'USD',
							store_fee: 0,
						},
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats full_refund events', () => {
			global.wcpaySettings.connect.country = 'FR';

			expect(
				mapTimelineEvents( [
					{
						amount_refunded: 1800,
						currency: 'EUR',
						datetime: 1586008266,
						deposit: null,
						type: 'full_refund',
						transaction_details: {
							customer_amount: 1800,
							customer_currency: 'EUR',
							customer_fee: 0,
							store_amount: 2164,
							store_currency: 'USD',
							store_fee: 0,
						},
					},
				] )
			).toMatchSnapshot();
		} );

		test( 'formats dispute_won events', () => {
			expect(
				mapTimelineEvents( [
					{
						amount: 2999,
						currency: 'USD',
						datetime: 1586017250,
						deposit: {
							arrival_date: 1586103650,
							id: 'dummy_po_5eaada696b2d3',
						},
						fee: -1500,
						type: 'dispute_won',
						transaction_details: {
							customer_amount: 2500,
							customer_currency: 'EUR',
							customer_fee: null,
							store_amount: 2999,
							store_currency: 'USD',
							store_fee: -1500,
						},
					},
				] )
			).toMatchSnapshot();
		} );
	} );
} );
