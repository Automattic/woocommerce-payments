/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import '../wc-subscriptions';

// Required by transformPrice, which the total-amount filter calls.
global.wcpayExpressCheckoutParams = {
	checkout: { currency_decimals: 2 },
};

const buildTrialSubscriptionItem = ( {
	name = 'Premium Plan',
	billingPeriod = 'month',
	trialLength = 14,
	signUpFees = '0',
	lineSubtotal = '0',
	lineTotal = '0',
} = {} ) => ( {
	name,
	totals: { line_subtotal: lineSubtotal, line_total: lineTotal },
	item_data: [],
	extensions: {
		subscriptions: {
			billing_period: billingPeriod,
			trial_length: trialLength,
			sign_up_fees: signUpFees,
		},
	},
} );

const buildSubscriptionSchedule = ( {
	billingPeriod = 'month',
	billingInterval = 1,
	nextPaymentDate = '2026-03-19',
	totalPrice = '1999',
	totalItems = '1999',
	totalTax = '0',
	totalShipping = '0',
	totalShippingTax = '0',
	currencyMinorUnit = 2,
	currencyPrefix = '$',
	currencySuffix = '',
	currencyDecimalSeparator = '.',
	currencyThousandSeparator = ',',
	taxLines = [],
	shippingRates,
} = {} ) => ( {
	billing_period: billingPeriod,
	billing_interval: billingInterval,
	next_payment_date: nextPaymentDate,
	totals: {
		total_price: totalPrice,
		total_items: totalItems,
		total_tax: totalTax,
		total_shipping: totalShipping,
		total_shipping_tax: totalShippingTax,
		currency_minor_unit: currencyMinorUnit,
		currency_prefix: currencyPrefix,
		currency_suffix: currencySuffix,
		currency_decimal_separator: currencyDecimalSeparator,
		currency_thousand_separator: currencyThousandSeparator,
		tax_lines: taxLines,
	},
	...( shippingRates ? { shipping_rates: shippingRates } : {} ),
} );

const buildTrialCart = ( {
	items,
	totalPrice = '0',
	subscriptions,
} = {} ) => ( {
	items: items || [ buildTrialSubscriptionItem() ],
	totals: {
		total_price: totalPrice,
		total_items: '0',
		total_tax: '0',
		total_shipping: '0',
		total_shipping_tax: '0',
		currency_minor_unit: 2,
		tax_lines: [],
	},
	extensions: {
		subscriptions: subscriptions || [ buildSubscriptionSchedule() ],
	},
} );

const regularCart = {
	items: [
		{
			name: 'T-Shirt',
			totals: { line_subtotal: '5000', line_total: '5000' },
			extensions: {},
		},
	],
	totals: {
		total_price: '5000',
		total_items: '5000',
		total_tax: '0',
		currency_minor_unit: 2,
	},
	extensions: {},
};

describe( 'ECE WC Subscriptions compatibility', () => {
	it( 'all filters pass through for regular (non-subscription) carts', () => {
		expect(
			applyFilters(
				'wcpay.express-checkout.total-amount',
				5000,
				regularCart
			)
		).toBe( 5000 );

		expect(
			applyFilters(
				'wcpay.express-checkout.is-cart-eligible',
				true,
				regularCart
			)
		).toBe( true );
		expect(
			applyFilters(
				'wcpay.express-checkout.is-cart-eligible',
				false,
				regularCart
			)
		).toBe( false );

		const existingRates = [ { rate_id: 'flat:1' } ];
		expect(
			applyFilters(
				'wcpay.express-checkout.shipping-rates',
				existingRates,
				regularCart
			)
		).toBe( existingRates );

		expect(
			applyFilters(
				'wcpay.express-checkout.shipping-package-id',
				0,
				regularCart,
				'flat:1'
			)
		).toBe( 0 );

		expect(
			applyFilters( 'wcpay.express-checkout.map-line-items', regularCart )
		).toBe( regularCart );
	} );

	it( 'total-amount and eligibility filters pass through when cart total is non-zero', () => {
		// Non-zero total means the subscription has a sign-up fee or other
		// upfront charges — $0-cart overrides don't activate.
		const cart = buildTrialCart( { totalPrice: '500' } );

		expect(
			applyFilters( 'wcpay.express-checkout.total-amount', 500, cart )
		).toBe( 500 );
		expect(
			applyFilters(
				'wcpay.express-checkout.is-cart-eligible',
				false,
				cart
			)
		).toBe( false );
	} );

	it( 'total-amount and eligibility filters pass through for subscriptions with sign-up fees (non-zero cart total)', () => {
		// Sign-up fees produce a non-zero cart total, so the $0-cart
		// overrides (total-amount, eligibility) don't activate.
		const cart = buildTrialCart( {
			items: [ buildTrialSubscriptionItem( { signUpFees: '500' } ) ],
			totalPrice: '500',
		} );

		expect(
			applyFilters( 'wcpay.express-checkout.total-amount', 500, cart )
		).toBe( 500 );
		expect(
			applyFilters(
				'wcpay.express-checkout.is-cart-eligible',
				true,
				cart
			)
		).toBe( true );
	} );

	describe( 'total-amount filter', () => {
		it( 'returns the recurring subscription total for a trial with $0 cart', () => {
			const cart = buildTrialCart();

			expect(
				applyFilters( 'wcpay.express-checkout.total-amount', 0, cart )
			).toBe( 1999 );
		} );

		it( 'includes shipping from selected rate in recurring total', () => {
			const cart = buildTrialCart( {
				subscriptions: [
					buildSubscriptionSchedule( {
						totalPrice: '2499',
						totalItems: '1999',
						shippingRates: [
							{
								package_id: 'sub_month_0',
								shipping_rates: [
									{
										rate_id: 'flat_rate:1',
										price: '500',
										taxes: '0',
										selected: true,
									},
								],
							},
						],
					} ),
				],
			} );

			expect(
				applyFilters( 'wcpay.express-checkout.total-amount', 0, cart )
			).toBe( 2499 );
		} );

		it( 'sums recurring totals across multiple subscription schedules', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Monthly',
						billingPeriod: 'month',
					} ),
					buildTrialSubscriptionItem( {
						name: 'Yearly',
						billingPeriod: 'year',
					} ),
				],
				subscriptions: [
					buildSubscriptionSchedule( {
						billingPeriod: 'month',
						totalPrice: '1000',
						totalItems: '1000',
					} ),
					buildSubscriptionSchedule( {
						billingPeriod: 'year',
						totalPrice: '5000',
						totalItems: '5000',
					} ),
				],
			} );

			expect(
				applyFilters( 'wcpay.express-checkout.total-amount', 0, cart )
			).toBe( 6000 );
		} );
	} );

	describe( 'is-cart-eligible filter', () => {
		it( 'makes a $0 trial cart eligible when recurring total is non-zero', () => {
			expect(
				applyFilters(
					'wcpay.express-checkout.is-cart-eligible',
					false,
					buildTrialCart()
				)
			).toBe( true );
		} );

		it( 'preserves existing eligibility without re-checking', () => {
			expect(
				applyFilters(
					'wcpay.express-checkout.is-cart-eligible',
					true,
					buildTrialCart()
				)
			).toBe( true );
		} );
	} );

	describe( 'shipping-rates filter', () => {
		const subscriptionWithShipping = ( rates ) =>
			buildSubscriptionSchedule( {
				shippingRates: [
					{
						package_id: 'sub_month_0',
						shipping_rates: rates,
					},
				],
			} );

		it( 'preserves existing shipping rates even for trial carts', () => {
			// Main cart rates take priority — subscription rates are only a
			// fallback for when shipping is deferred during a free trial.
			const mainRates = [ { rate_id: 'flat:1', name: 'Standard' } ];
			const cart = buildTrialCart( {
				subscriptions: [
					subscriptionWithShipping( [
						{ rate_id: 'flat:2', name: 'Sub Rate' },
					] ),
				],
			} );

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-rates',
					mainRates,
					cart
				)
			).toBe( mainRates );
		} );

		it( 'falls back to subscription shipping rates when main cart has none', () => {
			const subscriptionRates = [
				{ rate_id: 'flat_rate:1', name: 'Standard', price: '500' },
			];
			const cart = buildTrialCart( {
				subscriptions: [
					subscriptionWithShipping( subscriptionRates ),
				],
			} );

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-rates',
					[],
					cart
				)
			).toEqual( subscriptionRates );
		} );

		it( 'falls back to subscription shipping rates for trial with sign-up fee', () => {
			const subscriptionRates = [
				{
					rate_id: 'free_shipping:1',
					name: 'Free shipping',
					price: '0',
				},
				{
					rate_id: 'flat_rate:5',
					name: 'Express shipping',
					price: '1000',
				},
			];
			const cart = buildTrialCart( {
				items: [ buildTrialSubscriptionItem( { signUpFees: '200' } ) ],
				totalPrice: '217',
				subscriptions: [
					subscriptionWithShipping( subscriptionRates ),
				],
			} );

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-rates',
					[],
					cart
				)
			).toEqual( subscriptionRates );
		} );
	} );

	describe( 'shipping-package-id filter', () => {
		const cartWithShippingPackage = ( packageId, rates ) =>
			buildTrialCart( {
				subscriptions: [
					buildSubscriptionSchedule( {
						shippingRates: [
							{
								package_id: packageId,
								shipping_rates: rates,
							},
						],
					} ),
				],
			} );

		it( 'returns the subscription package ID matching the selected rate', () => {
			const cart = cartWithShippingPackage( 'sub_month_0', [
				{ rate_id: 'flat_rate:1' },
				{ rate_id: 'flat_rate:2' },
			] );

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-package-id',
					0,
					cart,
					'flat_rate:2'
				)
			).toBe( 'sub_month_0' );
		} );

		it( 'returns original package ID when rate is not found in subscriptions', () => {
			const cart = cartWithShippingPackage( 'sub_month_0', [
				{ rate_id: 'flat_rate:1' },
			] );

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-package-id',
					0,
					cart,
					'unknown_rate:99'
				)
			).toBe( 0 );
		} );

		it( 'returns the subscription package ID for trial with sign-up fee', () => {
			const cart = {
				...cartWithShippingPackage( 'sub_month_0', [
					{ rate_id: 'flat_rate:1' },
					{ rate_id: 'flat_rate:2' },
				] ),
				items: [ buildTrialSubscriptionItem( { signUpFees: '200' } ) ],
			};
			cart.totals.total_price = '217';

			expect(
				applyFilters(
					'wcpay.express-checkout.shipping-package-id',
					0,
					cart,
					'flat_rate:2'
				)
			).toBe( 'sub_month_0' );
		} );
	} );

	describe( 'map-line-items filter', () => {
		it( 'adds recurring metadata to trial items with sign-up fee without replacing prices', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Physical subscription',
						signUpFees: '200',
						lineSubtotal: '200',
						lineTotal: '200',
					} ),
				],
				totalPrice: '217',
				subscriptions: [
					buildSubscriptionSchedule( {
						totalPrice: '758',
						totalItems: '700',
						totalTax: '58',
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			// Must not mutate the original cart data.
			expect( result ).not.toBe( cart );
			expect( cart.items[ 0 ].name ).toBe( 'Physical subscription' );

			const item = result.items[ 0 ];
			// Should add (recurring) label.
			expect( item.name ).toBe( 'Physical subscription (recurring)' );
			// Should keep original prices (sign-up fee), not replace with recurring.
			expect( item.totals.line_subtotal ).toBe( '200' );
			expect( item.totals.line_total ).toBe( '200' );
			// Should add Recurring total metadata with recurring price.
			expect( item.item_data ).toContainEqual( {
				name: 'Recurring total',
				value: '$7.58 / month on 2026-03-19',
			} );

			// Should keep original cart totals (sign-up fee + tax).
			expect( result.totals.total_price ).toBe( '217' );
		} );

		it( 'formats plural billing interval in recurring metadata for sign-up fee carts', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Quarterly Plan',
						billingPeriod: 'month',
						signUpFees: '500',
						lineSubtotal: '500',
						lineTotal: '500',
					} ),
				],
				totalPrice: '500',
				subscriptions: [
					buildSubscriptionSchedule( {
						billingPeriod: 'month',
						billingInterval: 3,
						totalPrice: '2997',
						totalItems: '2997',
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			const item = result.items[ 0 ];
			expect( item.name ).toBe( 'Quarterly Plan (recurring)' );
			// Should keep original prices (sign-up fee).
			expect( item.totals.line_subtotal ).toBe( '500' );
			// Should format plural interval as "3 months".
			expect( item.item_data ).toContainEqual( {
				name: 'Recurring total',
				value: '$29.97 / 3 months on 2026-03-19',
			} );
		} );

		it( 'replaces $0 trial items with recurring amounts and metadata', () => {
			const cart = buildTrialCart();
			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			// Must not mutate the original cart data.
			expect( result ).not.toBe( cart );
			expect( cart.items[ 0 ].name ).toBe( 'Premium Plan' );

			const item = result.items[ 0 ];
			expect( item.name ).toBe( 'Premium Plan (recurring)' );
			expect( item.totals.line_subtotal ).toBe( '1999' );
			expect( item.totals.line_total ).toBe( '1999' );
			expect( item.item_data ).toContainEqual( {
				name: 'Recurring total',
				value: '$19.99 / month on 2026-03-19',
			} );

			expect( result.totals.total_price ).toBe( '1999' );
			expect( result.totals.total_items ).toBe( '1999' );
		} );

		it( 'splits recurring total evenly across items of the same billing period', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( { name: 'Seat 1' } ),
					buildTrialSubscriptionItem( { name: 'Seat 2' } ),
				],
				subscriptions: [
					buildSubscriptionSchedule( {
						totalPrice: '3000',
						totalItems: '3000',
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			expect( result.items[ 0 ].totals.line_subtotal ).toBe( '1500' );
			expect( result.items[ 1 ].totals.line_subtotal ).toBe( '1500' );
		} );

		it( 'includes shipping from selected rate in totals', () => {
			const cart = buildTrialCart( {
				subscriptions: [
					buildSubscriptionSchedule( {
						totalPrice: '2499',
						totalItems: '1999',
						shippingRates: [
							{
								package_id: 'sub_month_0',
								shipping_rates: [
									{
										rate_id: 'flat_rate:1',
										name: 'Standard',
										price: '500',
										taxes: '0',
										selected: true,
									},
								],
							},
						],
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			expect( result.totals.total_price ).toBe( '2499' );
			expect( result.totals.total_shipping ).toBe( '500' );
			expect( result.totals.total_items ).toBe( '1999' );
		} );

		it( 'handles multiple billing periods and aggregates totals', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Monthly Add-on',
						billingPeriod: 'month',
					} ),
					buildTrialSubscriptionItem( {
						name: 'Annual License',
						billingPeriod: 'year',
					} ),
				],
				subscriptions: [
					buildSubscriptionSchedule( {
						billingPeriod: 'month',
						totalPrice: '800',
						totalItems: '800',
						totalTax: '80',
						nextPaymentDate: '2026-03-19',
					} ),
					buildSubscriptionSchedule( {
						billingPeriod: 'year',
						totalPrice: '12000',
						totalItems: '12000',
						totalTax: '1200',
						nextPaymentDate: '2027-02-19',
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			expect( result.items[ 0 ].name ).toBe(
				'Monthly Add-on (recurring)'
			);
			expect( result.items[ 0 ].totals.line_subtotal ).toBe( '800' );
			expect( result.items[ 0 ].item_data ).toContainEqual( {
				name: 'Recurring total',
				value: '$8.00 / month on 2026-03-19',
			} );

			expect( result.items[ 1 ].name ).toBe(
				'Annual License (recurring)'
			);
			expect( result.items[ 1 ].totals.line_subtotal ).toBe( '12000' );
			expect( result.items[ 1 ].item_data ).toContainEqual( {
				name: 'Recurring total',
				value: '$120.00 / year on 2027-02-19',
			} );

			expect( result.totals.total_price ).toBe( '12800' );
			expect( result.totals.total_items ).toBe( '12800' );
			expect( result.totals.total_tax ).toBe( '1280' );
		} );

		it( 'does not duplicate metadata when multiple schedules share the same billing period', () => {
			// When subscription schedules differ only by interval/trial/length,
			// they share a billing_period. Each item must be processed only once.
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Monthly Sub',
						billingPeriod: 'month',
						signUpFees: '200',
						lineSubtotal: '200',
						lineTotal: '200',
					} ),
				],
				totalPrice: '200',
				subscriptions: [
					buildSubscriptionSchedule( {
						billingPeriod: 'month',
						billingInterval: 1,
						totalPrice: '700',
						totalItems: '700',
					} ),
					buildSubscriptionSchedule( {
						billingPeriod: 'month',
						billingInterval: 2,
						totalPrice: '1400',
						totalItems: '1400',
					} ),
				],
			} );

			const result = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);

			const item = result.items[ 0 ];
			// Name should have (recurring) only once.
			expect( item.name ).toBe( 'Monthly Sub (recurring)' );
			// Recurring total metadata should appear exactly once.
			const recurringEntries = item.item_data.filter(
				( d ) => d.name === 'Recurring total'
			);
			expect( recurringEntries ).toHaveLength( 1 );
		} );

		it( 'is idempotent when the filter runs on already-modified data', () => {
			const cart = buildTrialCart( {
				items: [
					buildTrialSubscriptionItem( {
						name: 'Physical subscription',
						signUpFees: '200',
						lineSubtotal: '200',
						lineTotal: '200',
					} ),
				],
				totalPrice: '200',
				subscriptions: [
					buildSubscriptionSchedule( {
						totalPrice: '758',
						totalItems: '700',
						totalTax: '58',
					} ),
				],
			} );

			// Run the filter twice — second call receives the first call's output.
			const firstPass = applyFilters(
				'wcpay.express-checkout.map-line-items',
				cart
			);
			const secondPass = applyFilters(
				'wcpay.express-checkout.map-line-items',
				firstPass
			);

			const item = secondPass.items[ 0 ];
			expect( item.name ).toBe( 'Physical subscription (recurring)' );
			const recurringEntries = item.item_data.filter(
				( d ) => d.name === 'Recurring total'
			);
			expect( recurringEntries ).toHaveLength( 1 );
		} );
	} );
} );
