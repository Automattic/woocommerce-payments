/**
 * Internal dependencies
 */
import { needsShipping, ReasonsNoShipping } from '../shipping-utils';

describe( 'needsShipping', () => {
	describe( 'product type filtering', () => {
		it( 'should return true for physical_product', () => {
			expect( needsShipping( 'fraudulent', 'physical_product' ) ).toBe(
				true
			);
		} );

		it( 'should return false for digital_product_or_service', () => {
			expect(
				needsShipping( 'fraudulent', 'digital_product_or_service' )
			).toBe( false );
		} );

		it( 'should return false for offline_service', () => {
			expect( needsShipping( 'fraudulent', 'offline_service' ) ).toBe(
				false
			);
		} );

		it( 'should return false for booking_reservation', () => {
			expect( needsShipping( 'fraudulent', 'booking_reservation' ) ).toBe(
				false
			);
		} );

		it( 'should return false for other', () => {
			expect( needsShipping( 'fraudulent', 'other' ) ).toBe( false );
		} );

		it( 'should return false for multiple', () => {
			expect( needsShipping( 'fraudulent', 'multiple' ) ).toBe( false );
		} );

		it( 'should return false for empty product type', () => {
			expect( needsShipping( 'fraudulent', '' ) ).toBe( false );
		} );

		it( 'should return false when product type is not provided', () => {
			expect( needsShipping( 'fraudulent' ) ).toBe( false );
		} );
	} );

	describe( 'dispute reason filtering', () => {
		it( 'should return false for duplicate reason even with physical_product', () => {
			expect( needsShipping( 'duplicate', 'physical_product' ) ).toBe(
				false
			);
		} );

		it( 'should return false for subscription_canceled reason even with physical_product', () => {
			expect(
				needsShipping( 'subscription_canceled', 'physical_product' )
			).toBe( false );
		} );

		it( 'should return false for credit_not_processed reason even with physical_product', () => {
			expect(
				needsShipping( 'credit_not_processed', 'physical_product' )
			).toBe( false );
		} );

		it( 'should return true for fraudulent reason with physical_product', () => {
			expect( needsShipping( 'fraudulent', 'physical_product' ) ).toBe(
				true
			);
		} );

		it( 'should return true for product_not_received reason with physical_product', () => {
			expect(
				needsShipping( 'product_not_received', 'physical_product' )
			).toBe( true );
		} );

		it( 'should return true for product_unacceptable reason with physical_product', () => {
			expect(
				needsShipping( 'product_unacceptable', 'physical_product' )
			).toBe( true );
		} );

		it( 'should return true for general reason with physical_product', () => {
			expect( needsShipping( 'general', 'physical_product' ) ).toBe(
				true
			);
		} );

		it( 'should return true for undefined reason with physical_product', () => {
			expect( needsShipping( undefined, 'physical_product' ) ).toBe(
				true
			);
		} );
	} );

	describe( 'ReasonsNoShipping constant', () => {
		it( 'should contain the expected reasons', () => {
			expect( ReasonsNoShipping ).toEqual( [
				'duplicate',
				'subscription_canceled',
				'credit_not_processed',
			] );
		} );
	} );
} );
