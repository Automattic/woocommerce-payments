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
			expect( result ).toHaveLength( 6 ); // Default fields + 3 specific fields
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'access_activity_log' );
			expect( result[ 2 ].label ).toBe( 'Subscription logs' );
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

		it( 'should return fields for credit_not_processed reason with refund_has_been_issued', () => {
			const fields = getRecommendedDocumentFields(
				'credit_not_processed',
				'refund_has_been_issued'
			);
			expect( fields ).toHaveLength( 6 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'customer_communication' );
			expect( fields[ 2 ].key ).toBe( 'customer_signature' );
			expect( fields[ 3 ].key ).toBe( 'refund_policy' );
			expect( fields[ 4 ].key ).toBe( 'service_documentation' );
			expect( fields[ 5 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fields for credit_not_processed reason with refund_was_not_owed', () => {
			const fields = getRecommendedDocumentFields(
				'credit_not_processed',
				'refund_was_not_owed'
			);
			expect( fields ).toHaveLength( 4 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'customer_communication' );
			expect( fields[ 2 ].key ).toBe( 'refund_policy' );
			expect( fields[ 3 ].key ).toBe( 'uncategorized_file' );
			// Should not include customer_signature or service_documentation
			expect(
				fields.find( ( field ) => field.key === 'customer_signature' )
			).toBeUndefined();
			expect(
				fields.find(
					( field ) => field.key === 'service_documentation'
				)
			).toBeUndefined();
		} );

		it( 'should return fields for duplicate reason with is_duplicate status', () => {
			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_duplicate'
			);
			expect( fields ).toHaveLength( 3 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 0 ].label ).toBe( 'Order receipt' );
			expect( fields[ 1 ].key ).toBe( 'uncategorized_file' ); // Refund receipt
			expect( fields[ 1 ].label ).toBe( 'Refund receipt' );
			expect( fields[ 1 ].description ).toBe(
				'A confirmation that the refund was processed.'
			);
			expect( fields[ 2 ].key ).toBe( 'refund_policy' );
			expect( fields[ 2 ].label ).toBe( 'Refund policy' );
		} );

		it( 'should return fields for duplicate reason with is_not_duplicate status', () => {
			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_not_duplicate'
			);
			expect( fields ).toHaveLength( 5 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'duplicate_charge_documentation' );
			expect( fields[ 1 ].label ).toBe( 'Any additional receipts' );
			expect( fields[ 1 ].description ).toBe(
				'Receipt(s) for any other order(s) from this customer.'
			);
			expect( fields[ 2 ].key ).toBe( 'customer_communication' );
			expect( fields[ 3 ].key ).toBe( 'refund_policy' );
			expect( fields[ 3 ].label ).toBe( 'Refund policy' );
			expect( fields[ 3 ].description ).toBe(
				'A screenshot of the refund policy for the provided service.'
			);
			expect( fields[ 4 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fields for duplicate reason with missing duplicate status', () => {
			const fields = getRecommendedDocumentFields( 'duplicate' );
			expect( fields ).toHaveLength( 5 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'duplicate_charge_documentation' );
			expect( fields[ 1 ].label ).toBe( 'Any additional receipts' );
			expect( fields[ 1 ].description ).toBe(
				'Receipt(s) for any other order(s) from this customer.'
			);
			expect( fields[ 2 ].key ).toBe( 'customer_communication' );
			expect( fields[ 3 ].key ).toBe( 'refund_policy' );
			expect( fields[ 3 ].label ).toBe( 'Refund policy' );
			expect( fields[ 3 ].description ).toBe(
				'A screenshot of the refund policy for the provided service.'
			);
			expect( fields[ 4 ].key ).toBe( 'uncategorized_file' );
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

		describe( 'subscription_canceled with productType variations', () => {
			it( 'should return fields with subscription logs for subscription_canceled with single product type', () => {
				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'physical_product'
				);
				expect( result ).toHaveLength( 6 ); // Default fields + 3 specific fields
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 2 ].label ).toBe( 'Subscription logs' );
				expect( result[ 2 ].description ).toBe(
					'Order notes or the history of related orders. This should clearly show successful renewals before the dispute.'
				);
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
			} );

			it( 'should return fields with subscription logs for subscription_canceled with digital product', () => {
				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'digital_product_or_service'
				);
				expect( result ).toHaveLength( 6 );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 2 ].label ).toBe( 'Subscription logs' );
			} );

			it( 'should return fields without subscription logs for subscription_canceled with multiple product types', () => {
				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'multiple'
				);
				expect( result ).toHaveLength( 5 ); // Default fields + 2 specific fields (no subscription logs)
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'refund_policy' );
				expect( result[ 3 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 4 ].key ).toBe( 'uncategorized_file' );

				// Verify subscription logs are NOT included
				const hasSubscriptionLogs = result.some(
					( field ) => field.key === 'access_activity_log'
				);
				expect( hasSubscriptionLogs ).toBe( false );
			} );

			it( 'should return fields with subscription logs for subscription_canceled with booking_reservation type', () => {
				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'booking_reservation'
				);
				expect( result ).toHaveLength( 6 );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 2 ].label ).toBe( 'Subscription logs' );
			} );

			it( 'should return fields with subscription logs for subscription_canceled with offline_service type', () => {
				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'offline_service'
				);
				expect( result ).toHaveLength( 6 );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 2 ].label ).toBe( 'Subscription logs' );
			} );
		} );
	} );
} );
