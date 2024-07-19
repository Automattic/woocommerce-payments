/**
 * Internal dependencies
 */
import {
	transformPrice,
	transformCartDataForShippingOptions,
} from '../wc-to-stripe';

global.wcpayPaymentRequestParams = {};
global.wcpayPaymentRequestParams.checkout = {};

describe( 'wc-to-stripe transformers', () => {
	describe( 'transformPrice', () => {
		afterEach( () => {
			delete global.wcpayPaymentRequestParams.checkout.currency_decimals;
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
			global.wcpayPaymentRequestParams.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			expect( transformPrice( 18, { currency_minor_unit: 0 } ) ).toBe(
				18
			);
		} );

		it( 'transforms the price if the currency a zero decimal currency (e.g.: Yen) but it is configured with one decimal', () => {
			global.wcpayPaymentRequestParams.checkout.currency_decimals = 0;
			// with zero decimals, `18` would mean `18`.
			// But since Stripe expects the price to be in the minimum currency amount, the return value should be `18`
			expect( transformPrice( 180, { currency_minor_unit: 1 } ) ).toBe(
				18
			);
		} );
	} );

	describe( 'transformCartDataForShippingOptions', () => {
		it( 'transforms shipping rates', () => {
			expect(
				transformCartDataForShippingOptions( {
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
					amount: 1000,
					detail: '',
					id: 'flat_rate:14',
					label: 'CA Flat rate',
				},
				{
					amount: 350,
					detail: '',
					id: 'local_pickup:15',
					label: 'Local pickup',
				},
				{
					amount: 0,
					detail: '',
					id: 'free_shipping:13',
					label: 'Free shipping',
				},
			] );
		} );

		it( 'transforms shipping options for local pickup', () => {
			expect(
				transformCartDataForShippingOptions( {
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
					detail:
						'42 Wallaby Way, Sydney New South Wales 200, Australia - Ask for P. Sherman',
					id: 'pickup_location:1',
					label:
						'Local pickup – options coming from WooCommerce Blocks (Australian warehouse)',
				},
			] );
		} );
	} );
} );
