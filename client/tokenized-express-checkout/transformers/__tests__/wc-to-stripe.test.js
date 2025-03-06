/**
 * Internal dependencies
 */
import {
	transformPrice,
	transformCartDataForShippingRates,
	transformCartDataForDisplayItems,
} from '../wc-to-stripe';

global.wcpayExpressCheckoutParams = {};
global.wcpayExpressCheckoutParams.checkout = {};

describe( 'wc-to-stripe transformers', () => {
	describe( 'transformCartDataForDisplayItems', () => {
		it( 'transforms the cart items and their names, if they contain special characters', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [
						{
							key: '6fd9b4da889ae534ceae47561b939f24',
							id: 214,
							type: 'simple',
							quantity: 2,
							name: 'Deposit',
							variation: [],
							item_data: [
								{
									name: 'Payment Plan',
									value: 'Deposit 30',
									display: '',
								},
								{
									key: 'Payable In Total',
									value: '&#36;45.00 payable over 20 days',
								},
							],
							prices: {
								price: '4500',
								regular_price: '5000',
								sale_price: '4500',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '45000000',
									regular_price: '50000000',
									sale_price: '45000000',
								},
							},
							totals: {
								line_subtotal: '1350',
								line_subtotal_tax: 0,
								line_total: '4500',
								line_total_tax: '388',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								'woocommerce-deposits': {
									is_deposit: true,
									has_payment_plan: true,
									plan_schedule: [
										{
											schedule_id: '2',
											schedule_index: '0',
											plan_id: '2',
											amount: '30',
											interval_amount: '0',
											interval_unit: '0',
										},
										{
											schedule_id: '3',
											schedule_index: '1',
											plan_id: '2',
											amount: '70',
											interval_amount: '20',
											interval_unit: 'day',
										},
									],
								},
								bundles: [],
							},
						},
						{
							key: '30e94626ff41df1be0572e19f249746f',
							id: 44,
							type: 'subscription',
							quantity: 1,
							name: 'Physical subscription',
							variation: [],
							prices: {
								price: '4500',
								regular_price: '5000',
								sale_price: '4500',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '45000000',
									regular_price: '50000000',
									sale_price: '45000000',
								},
							},
							totals: {
								line_subtotal: '4500',
								line_subtotal_tax: '0',
								line_total: '4500',
								line_total_tax: '0',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								subscriptions: {
									billing_period: 'month',
									billing_interval: 1,
									subscription_length: 0,
									trial_length: 0,
									trial_period: 'day',
									is_resubscribe: false,
									switch_type: null,
									synchronization: null,
									sign_up_fees: '300',
									sign_up_fees_tax: '33',
								},
								addons: [],
							},
						},
						{
							key: '4cf7f86c98b84855e3d5811a5712b35d',
							id: 66,
							type: 'booking',
							quantity: 1,
							name: 'WC Bookings &#8211; Equipment Rental',
							variation: [],
							item_data: [
								{
									name: 'Booking Date',
									value: 'August 3, 2024',
									display: '',
								},
								{
									name: 'Qty (Sample person)',
									value: '1',
									display: '',
								},
								{
									name: 'Booking Type',
									value:
										'Black folding chairs (Sample resource)',
									display: '',
								},
							],
							prices: {
								price: '150',
								regular_price: '150',
								sale_price: '150',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '1500000',
									regular_price: '1500000',
									sale_price: '1500000',
								},
							},
							totals: {
								line_subtotal: '150',
								line_subtotal_tax: '0',
								line_total: '150',
								line_total_tax: '0',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								addons: [],
							},
						},
					],
					shipping_rates: [],
					totals: {
						currency_code: 'USD',
						currency_decimal_separator: '.',
						currency_minor_unit: 2,
						currency_prefix: '$',
						currency_suffix: '',
						currency_symbol: '$',
						currency_thousand_separator: ',',
						tax_lines: [],
						total_discount: '0',
						total_discount_tax: '0',
						total_fees: '0',
						total_fees_tax: '0',
						total_items: '6000',
						total_items_tax: '0',
						total_price: '6000',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_tax: '0',
					},
				} )
			).toStrictEqual( [
				{
					amount: 1350,
					name:
						'Deposit (x2) - Payment Plan: Deposit 30, Payable In Total: $45.00 payable over 20 days',
				},
				{ amount: 4500, name: 'Physical subscription' },
				{
					amount: 150,
					name:
						// eslint-disable-next-line max-len
						'WC Bookings – Equipment Rental - Booking Date: August 3, 2024, Qty (Sample person): 1, Booking Type: Black folding chairs (Sample resource)',
				},
			] );
		} );

		it( 'transforms the tax amount when present', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '545',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '545',
						total_tax: '545',
						tax_lines: [
							{
								name: 'CA-Tax-Rate',
								price: '545',
								rate: '11%',
							},
						],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [ { amount: 545, name: 'Tax' } ] );
		} );

		it( 'transforms the tax amount when not present', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '0',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '0',
						total_tax: '0',
						tax_lines: [],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [] );
		} );

		it( 'does not return line items when there is a discrepancy with the totals', () => {
			expect(
				transformCartDataForDisplayItems( {
					items: [
						{
							key: '6fd9b4da889ae534ceae47561b939f24',
							id: 214,
							type: 'simple',
							quantity: 2,
							name: 'Deposit',
							variation: [],
							item_data: [
								{
									name: 'Payment Plan',
									value: 'Deposit 30',
									display: '',
								},
								{
									key: 'Payable In Total',
									value: '&#36;45.00 payable over 20 days',
								},
							],
							prices: {
								price: '4500',
								regular_price: '5000',
								sale_price: '4500',
								price_range: null,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
								raw_prices: {
									precision: 6,
									price: '45000000',
									regular_price: '50000000',
									sale_price: '45000000',
								},
							},
							totals: {
								line_subtotal: '1350',
								line_subtotal_tax: 0,
								line_total: '4500',
								line_total_tax: '388',
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							catalog_visibility: 'visible',
							extensions: {
								'woocommerce-deposits': {
									is_deposit: true,
									has_payment_plan: true,
									plan_schedule: [
										{
											schedule_id: '2',
											schedule_index: '0',
											plan_id: '2',
											amount: '30',
											interval_amount: '0',
											interval_unit: '0',
										},
										{
											schedule_id: '3',
											schedule_index: '1',
											plan_id: '2',
											amount: '70',
											interval_amount: '20',
											interval_unit: 'day',
										},
									],
								},
								bundles: [],
							},
						},
					],
					shipping_rates: [],
					totals: {
						total_items: '0',
						total_items_tax: '0',
						total_fees: '0',
						total_fees_tax: '0',
						total_discount: '0',
						total_discount_tax: '0',
						total_shipping: '0',
						total_shipping_tax: '0',
						total_price: '0',
						total_tax: '0',
						tax_lines: [],
						currency_code: 'USD',
						currency_symbol: '$',
						currency_minor_unit: 2,
						currency_decimal_separator: '.',
						currency_thousand_separator: ',',
						currency_prefix: '$',
						currency_suffix: '',
					},
				} )
			).toStrictEqual( [] );
		} );
	} );

	describe( 'transformPrice', () => {
		afterEach( () => {
			delete global.wcpayExpressCheckoutParams.checkout.currency_decimals;
		} );

		it( 'transforms the price', () => {
			expect( transformPrice( 180, { currency_minor_unit: 2 } ) ).toBe(
				180
			);
		} );

		it( 'transforms the price if the currency is configured with one decimal', () => {
			// with one decimal, `180` would mean `18.0`.
			// But since Stripe expects the price to be in cents, the return value should be `1800`
			expect( transformPrice( 180, { currency_minor_unit: 1 } ) ).toBe(
				1800
			);
		} );

		it( 'transforms the price if the currency is configured with two decimals', () => {
			// with two decimals, `1800` would mean `18.00`.
			// But since Stripe expects the price to be in cents, the return value should be `1800`
			expect( transformPrice( 1800, { currency_minor_unit: 2 } ) ).toBe(
				1800
			);
		} );

		it( 'transforms the price if the currency is a zero decimal currency (e.g.: Yen)', () => {
			global.wcpayExpressCheckoutParams.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			expect( transformPrice( 18, { currency_minor_unit: 0 } ) ).toBe(
				18
			);
		} );

		it( 'transforms the price if the currency a zero decimal currency (e.g.: Yen) but it is configured with one decimal', () => {
			global.wcpayExpressCheckoutParams.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			// But since Stripe expects the price to be in the minimum currency amount, the return value should be `18`
			expect( transformPrice( 180, { currency_minor_unit: 1 } ) ).toBe(
				18
			);
		} );
	} );

	describe( 'transformCartDataForShippingRates', () => {
		it( 'transforms shipping rates, placing the selected one at the top of the list', () => {
			expect(
				transformCartDataForShippingRates( {
					shipping_rates: [
						{
							package_id: 0,
							name: 'Shipment 1',
							destination: {},
							items: [
								{
									key: 'aab3238922bcc25a6f606eb525ffdc56',
									name: 'Beanie',
									quantity: 1,
								},
							],
							shipping_rates: [
								{
									rate_id: 'flat_rate:14',
									name: 'CA Flat rate',
									description: '',
									delivery_time: '',
									price: '1000',
									taxes: '300',
									instance_id: 14,
									method_id: 'flat_rate',
									meta_data: [
										{
											key: 'Items',
											value: 'Beanie &times; 1',
										},
									],
									selected: false,
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
								{
									rate_id: 'local_pickup:15',
									name: 'Local pickup',
									description: '',
									delivery_time: '',
									price: '350',
									taxes: '105',
									instance_id: 15,
									method_id: 'local_pickup',
									meta_data: [
										{
											key: 'Items',
											value: 'Beanie &times; 1',
										},
									],
									selected: true,
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
								{
									rate_id: 'free_shipping:13',
									name: 'Free shipping',
									description: '',
									delivery_time: '',
									price: '0',
									taxes: '0',
									instance_id: 13,
									method_id: 'free_shipping',
									meta_data: [
										{
											key: 'Items',
											value: 'Beanie &times; 1',
										},
									],
									selected: false,
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
							],
						},
					],
				} )
			).toEqual( [
				{
					amount: 350,
					deliveryEstimate: '',
					id: 'local_pickup:15',
					displayName: 'Local pickup',
				},
				{
					amount: 1000,
					deliveryEstimate: '',
					id: 'flat_rate:14',
					displayName: 'CA Flat rate',
				},
				{
					amount: 0,
					deliveryEstimate: '',
					id: 'free_shipping:13',
					displayName: 'Free shipping',
				},
			] );
		} );

		it( 'does not return more than 9 items', () => {
			const rates = transformCartDataForShippingRates( {
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								name: 'Beanie',
								quantity: 1,
							},
						],
						shipping_rates: [
							{
								rate_id: 'flat_rate:5',
								name: 'Express shipping',
								description: '',
								delivery_time: '',
								price: '1170',
								taxes: '97',
								instance_id: 5,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:10',
								name: 'Flat rate 1',
								description: '',
								delivery_time: '',
								price: '100',
								taxes: '8',
								instance_id: 10,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:11',
								name: 'Flat rate 2',
								description: '',
								delivery_time: '',
								price: '200',
								taxes: '17',
								instance_id: 11,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:12',
								name: 'Flat rate 3',
								description: '',
								delivery_time: '',
								price: '300',
								taxes: '25',
								instance_id: 12,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:13',
								name: 'Flat rate 4',
								description: '',
								delivery_time: '',
								price: '400',
								taxes: '33',
								instance_id: 13,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:14',
								name: 'Flat rate 5',
								description: '',
								delivery_time: '',
								price: '500',
								taxes: '41',
								instance_id: 14,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:15',
								name: 'Flat rate 6',
								description: '',
								delivery_time: '',
								price: '600',
								taxes: '50',
								instance_id: 15,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:16',
								name: 'Flat rate 7',
								description: '',
								delivery_time: '',
								price: '700',
								taxes: '58',
								instance_id: 16,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:17',
								name: 'Flat rate 8',
								description: '',
								delivery_time: '',
								price: '800',
								taxes: '66',
								instance_id: 17,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'flat_rate:18',
								name: 'Flat rate 9',
								description: '',
								delivery_time: '',
								price: '900',
								taxes: '74',
								instance_id: 18,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'pickup_location:0',
								name: '(Palo Alto, CA)',
								description: '',
								delivery_time: '',
								price: '100',
								taxes: '8',
								instance_id: 0,
								method_id: 'pickup_location',
								selected: false,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
							{
								rate_id: 'free_shipping:1',
								name: 'Free shipping',
								description: '',
								delivery_time: '',
								price: '0',
								taxes: '0',
								instance_id: 1,
								method_id: 'free_shipping',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 1',
									},
								],
								selected: true,
								currency_code: 'USD',
								currency_symbol: '$',
								currency_minor_unit: 2,
								currency_decimal_separator: '.',
								currency_thousand_separator: ',',
								currency_prefix: '$',
								currency_suffix: '',
							},
						],
					},
				],
			} );

			expect( rates ).toHaveLength( 9 );
			// let's also ensure it contains the "selected" rate.
			expect( rates ).toContainEqual( {
				amount: 0,
				deliveryEstimate: '',
				id: 'free_shipping:1',
				displayName: 'Free shipping',
			} );
		} );

		it( 'transforms shipping options for local pickup', () => {
			expect(
				transformCartDataForShippingRates( {
					shipping_rates: [
						{
							package_id: 0,
							name: 'Shipment 1',
							destination: {},
							items: [
								{
									key: 'aab3238922bcc25a6f606eb525ffdc56',
									name: 'Beanie',
									quantity: 1,
								},
							],
							shipping_rates: [
								{
									rate_id: 'pickup_location:1',
									name:
										'Local pickup &#8211; options coming from WooCommerce Blocks (Australian warehouse)',
									description: '',
									delivery_time: '',
									price: '0',
									taxes: '0',
									instance_id: 0,
									method_id: 'pickup_location',
									meta_data: [
										{
											key: 'pickup_location',
											value: 'Australian warehouse',
										},
										{
											key: 'pickup_address',
											value:
												'42 Wallaby Way, Sydney New South Wales 200, Australia',
										},
										{
											key: 'pickup_details',
											value: 'Ask for P. Sherman',
										},
										{
											key: 'Items',
											value: 'Beanie &times; 1',
										},
									],
									selected: false,
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
							],
						},
					],
				} )
			).toEqual( [
				{
					amount: 0,
					deliveryEstimate:
						'42 Wallaby Way, Sydney New South Wales 200, Australia - Ask for P. Sherman',
					id: 'pickup_location:1',
					displayName:
						'Local pickup – options coming from WooCommerce Blocks (Australian warehouse)',
				},
			] );
		} );
	} );
} );
