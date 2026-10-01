/**
 * Internal dependencies
 */
import {
	transformStripeShippingAddressForStoreApi,
	transformStripePaymentMethodForStoreApi,
} from '../stripe-to-wc';

describe( 'stripe-to-wc transformers', () => {
	describe( 'transformStripeShippingAddressForStoreApi', () => {
		it( 'preserves the internal space in multi-word HK regions delivered in postal_code', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Jane Doe',
				{
					line1: '1 Tai Po Road',
					city: 'Tai Po',
					state: '',
					postal_code: 'New Territories',
					country: 'HK',
				}
			);

			// The HK region travels in postal_code (Apple Pay bug). It must reach PHP
			// with its space intact so the HK normalization can match "new territories".
			expect( result.postcode ).toBe( 'New Territories' );
		} );

		it( 'preserves multi-word "Hong Kong Island" delivered in postal_code', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Jane Doe',
				{
					postal_code: 'Hong Kong Island',
					country: 'HK',
				}
			);

			expect( result.postcode ).toBe( 'Hong Kong Island' );
		} );

		it( 'preserves a Chinese (中文) HK region delivered in postal_code', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Jane Doe',
				{
					postal_code: '新界',
					country: 'HK',
				}
			);

			expect( result.postcode ).toBe( '新界' );
		} );

		it( 'passes a GB postcode through unchanged (PHP handles redaction padding)', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Jane Doe',
				{
					postal_code: 'N1 C',
					country: 'GB',
				}
			);

			// JS no longer strips spaces; PHP get_normalized_postal_code() removes
			// whitespace and pads redacted GB/CA postcodes server-side.
			expect( result.postcode ).toBe( 'N1 C' );
		} );

		it( 'returns an empty postcode when postal_code is absent', () => {
			const result = transformStripeShippingAddressForStoreApi(
				'Jane Doe',
				{
					line1: '1 Tai Po Road',
					city: 'Tai Po',
					country: 'HK',
				}
			);

			expect( result.postcode ).toBe( '' );
		} );
	} );

	describe( 'transformStripePaymentMethodForStoreApi', () => {
		const basePaymentData = ( address ) => ( {
			billingDetails: {
				name: 'Jane Doe',
				email: 'jane@example.com',
				phone: '5551234567',
				address,
			},
			expressPaymentType: 'apple_pay',
		} );

		it( 'preserves a multi-word HK region in the billing postcode', () => {
			const result = transformStripePaymentMethodForStoreApi(
				basePaymentData( {
					line1: '1 Tai Po Road',
					city: 'Tai Po',
					state: '',
					postal_code: 'New Territories',
					country: 'HK',
				} ),
				'pm_123'
			);

			expect( result.billing_address.postcode ).toBe( 'New Territories' );
		} );

		it( 'preserves a Chinese (中文) HK region in the billing postcode', () => {
			const result = transformStripePaymentMethodForStoreApi(
				basePaymentData( {
					postal_code: '新界',
					country: 'HK',
				} ),
				'pm_123'
			);

			expect( result.billing_address.postcode ).toBe( '新界' );
		} );
	} );
} );
