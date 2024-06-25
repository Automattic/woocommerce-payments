/**
 * Internal dependencies
 */
import { transformPrice } from '../wc-to-stripe';

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
} );
