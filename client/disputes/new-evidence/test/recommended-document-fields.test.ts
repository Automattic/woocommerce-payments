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
			expect( result ).toHaveLength( 6 ); // Default fields + fields for the "general" reason
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'access_activity_log' );
			expect( result[ 3 ].key ).toBe( 'refund_policy' );
			expect( result[ 4 ].key ).toBe( 'service_documentation' );
			expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
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
			expect( result[ 2 ].key ).toBe( 'access_activity_log' );
			expect( result[ 3 ].key ).toBe( 'refund_policy' );
			expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
			expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should include proper labels and descriptions for general reason fields', () => {
			const result = getRecommendedDocumentFields( 'general' );

			// Check access_activity_log field
			const accessActivityLogField = result.find(
				( field ) => field.key === 'access_activity_log'
			);
			expect( accessActivityLogField ).toBeDefined();
			expect( accessActivityLogField?.label ).toBe(
				'Proof of active subscription'
			);
			expect( accessActivityLogField?.description ).toBe(
				'Such as billing history, subscription status, or cancellation logs.'
			);

			// Check refund_policy field
			const refundPolicyField = result.find(
				( field ) => field.key === 'refund_policy'
			);
			expect( refundPolicyField ).toBeDefined();
			expect( refundPolicyField?.label ).toBe( 'Store refund policy' );
			expect( refundPolicyField?.description ).toBe(
				"A screenshot of your store's refund policy."
			);

			// Check service_documentation field (which for 'general' is labeled as 'Terms of service')
			const serviceDocField = result.find(
				( field ) => field.key === 'service_documentation'
			);
			expect( serviceDocField ).toBeDefined();
			expect( serviceDocField?.label ).toBe( 'Terms of service' );
			expect( serviceDocField?.description ).toBe(
				"A screenshot of your store's terms of service."
			);

			expect( result ).toHaveLength( 6 );
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'access_activity_log' );
			expect( result[ 3 ].key ).toBe( 'refund_policy' );
			expect( result[ 4 ].key ).toBe( 'service_documentation' );
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
