/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { getRecommendedDocumentFields } from '../recommended-document-fields';
import { RecommendedDocument } from '../types';

describe( 'Recommended Documents', () => {
	describe( 'getRecommendedDocumentFields', () => {
		it( 'should return default fields when no specific reason is provided', () => {
			const result = getRecommendedDocumentFields( '' );
			expect( result ).toHaveLength( 4 ); // Default fields
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'customer_signature' );
			expect( result[ 3 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fields for product_unacceptable reason', () => {
			const result = getRecommendedDocumentFields(
				'product_unacceptable'
			);
			expect( result ).toHaveLength( 6 ); // Default fields + 2 specific fields
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'customer_signature' );
			expect( result[ 3 ].key ).toBe( 'service_documentation' );
			expect( result[ 4 ].key ).toBe( 'refund_policy' );
			expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fields for product_not_received reason', () => {
			const result = getRecommendedDocumentFields(
				'product_not_received'
			);
			expect( result ).toHaveLength( 5 ); // Default fields + 1 specific field
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'customer_signature' );
			expect( result[ 3 ].key ).toBe( 'refund_policy' );
			expect( result[ 4 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fields for subscription_canceled reason', () => {
			const result = getRecommendedDocumentFields(
				'subscription_canceled'
			);
			expect( result ).toHaveLength( 6 ); // Default fields + 2 specific fields
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'customer_signature' );
			expect( result[ 3 ].key ).toBe( 'cancellation_policy' );
			expect( result[ 4 ].key ).toBe( 'access_activity_log' );
			expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should maintain correct order of fields', () => {
			const result = getRecommendedDocumentFields(
				'product_unacceptable'
			);
			const order = result.map(
				( field: RecommendedDocument ) => field.key
			);
			expect( order ).toEqual( [
				'receipt',
				'customer_communication',
				'customer_signature',
				'service_documentation',
				'refund_policy',
				'uncategorized_file',
			] );
		} );
	} );
} );
