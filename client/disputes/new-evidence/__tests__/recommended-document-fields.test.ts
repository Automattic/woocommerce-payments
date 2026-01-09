/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { getRecommendedDocumentFields } from '../recommended-document-fields';
import { RecommendedDocument } from '../types';

declare const global: {
	wcpaySettings: {
		featureFlags: {
			isDisputeAdditionalEvidenceTypesEnabled: boolean;
		};
	};
};

describe( 'Recommended Documents', () => {
	const originalWcpaySettings = global.wcpaySettings;

	beforeEach( () => {
		global.wcpaySettings = {
			featureFlags: {
				isDisputeAdditionalEvidenceTypesEnabled: false,
			},
		};
	} );

	afterEach( () => {
		global.wcpaySettings = originalWcpaySettings;
	} );
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
			// When feature flag is OFF, uses fallback fields.
			const result = getRecommendedDocumentFields(
				'subscription_canceled'
			);
			expect( result ).toHaveLength( 6 ); // Default fields + 3 specific fields
			expect( result[ 0 ].key ).toBe( 'receipt' );
			expect( result[ 1 ].key ).toBe( 'customer_communication' );
			expect( result[ 2 ].key ).toBe( 'access_activity_log' );
			expect( result[ 2 ].label ).toBe( 'Proof of active subscription' );
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

		it( 'should return matrix fields for duplicate + booking_reservation + is_duplicate', () => {
			global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_duplicate',
				'booking_reservation'
			);
			expect( fields ).toHaveLength( 4 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 0 ].label ).toBe( 'Order receipt' );
			expect( fields[ 1 ].key ).toBe( 'uncategorized_file' ); // Refund receipt
			expect( fields[ 1 ].label ).toBe( 'Refund receipt' );
			expect( fields[ 1 ].description ).toBe(
				'A confirmation that the refund was processed.'
			);
			expect( fields[ 2 ].key ).toBe( 'refund_policy' );
			expect( fields[ 2 ].label ).toBe( 'Refund policy' );
			expect( fields[ 3 ].key ).toBe( 'customer_communication' ); // Base field
		} );

		it( 'should return matrix fields for duplicate + booking_reservation + is_not_duplicate', () => {
			global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_not_duplicate',
				'booking_reservation'
			);
			expect( fields ).toHaveLength( 5 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'duplicate_charge_documentation' );
			expect( fields[ 1 ].label ).toBe( 'Any additional receipts' );
			expect( fields[ 1 ].description ).toBe(
				'Receipt(s) for any other order(s) from this customer.'
			);
			expect( fields[ 2 ].key ).toBe( 'customer_communication' ); // Base field merged
			expect( fields[ 3 ].key ).toBe( 'refund_policy' );
			expect( fields[ 3 ].label ).toBe( 'Refund policy' );
			expect( fields[ 3 ].description ).toBe(
				"A screenshot of your store's refund policy."
			);
			expect( fields[ 4 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fallback fields for duplicate + is_duplicate when feature flag is disabled', () => {
			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_duplicate'
			);
			expect( fields ).toHaveLength( 6 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'customer_communication' );
			expect( fields[ 2 ].key ).toBe( 'access_activity_log' );
			expect( fields[ 2 ].label ).toBe( 'Proof of active subscription' );
			expect( fields[ 3 ].key ).toBe( 'refund_policy' );
			expect( fields[ 4 ].key ).toBe( 'cancellation_policy' );
			expect( fields[ 5 ].key ).toBe( 'uncategorized_file' );
		} );

		it( 'should return fallback fields for duplicate + is_not_duplicate when feature flag is disabled', () => {
			const fields = getRecommendedDocumentFields(
				'duplicate',
				undefined,
				'is_not_duplicate'
			);
			expect( fields ).toHaveLength( 4 );
			expect( fields[ 0 ].key ).toBe( 'receipt' );
			expect( fields[ 1 ].key ).toBe( 'customer_communication' );
			expect( fields[ 2 ].key ).toBe( 'refund_policy' );
			expect( fields[ 2 ].label ).toBe( 'Store refund policy' );
			expect( fields[ 3 ].key ).toBe( 'uncategorized_file' );
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

		describe( 'evidence matrix with feature flag', () => {
			it( 'should return matrix fields for fraudulent + booking_reservation when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'fraudulent',
					undefined,
					undefined,
					'booking_reservation'
				);

				expect( result ).toHaveLength( 2 );
				expect( result[ 0 ].key ).toBe( 'uncategorized_file' );
				expect( result[ 0 ].label ).toBe(
					'Prior undisputed transaction history'
				);
				expect( result[ 0 ].description ).toBe(
					'Proof of past undisputed transactions from the same customer, with matching billing and device details.'
				);
				expect( result[ 1 ].key ).toBe( 'customer_communication' ); // Base field
			} );

			it( 'should return default fraudulent fields when feature flag is disabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = false;

				const result = getRecommendedDocumentFields(
					'fraudulent',
					undefined,
					undefined,
					'booking_reservation'
				);

				// Should return default fraudulent fields, not matrix fields
				expect( result ).toHaveLength( 5 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'customer_signature' );
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'uncategorized_file' );
			} );

			it( 'should return default fields for fraudulent + physical_product even when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'fraudulent',
					undefined,
					undefined,
					'physical_product'
				);

				// Should fall back to default fraudulent fields since no matrix entry exists
				expect( result ).toHaveLength( 5 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 2 ].key ).toBe( 'customer_signature' );
			} );

			it( 'should return default fields for fraudulent when no product type is provided', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields( 'fraudulent' );

				// Should fall back to default fraudulent fields
				expect( result ).toHaveLength( 5 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
			} );

			it( 'should return matrix fields for subscription_canceled + multiple when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'multiple'
				);

				// Matrix entry for subscription_canceled + multiple (no subscription logs)
				expect( result ).toHaveLength( 5 );
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

			it( 'should return matrix fields for subscription_canceled + other when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'other'
				);

				// Matrix entry for subscription_canceled + other (simplified fields + base Customer communication)
				expect( result ).toHaveLength( 3 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 0 ].label ).toBe( 'Proof of Purchase' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' ); // Base field
				expect( result[ 2 ].key ).toBe( 'uncategorized_file' );
				expect( result[ 2 ].label ).toBe( 'Order details' );
			} );

			it( 'should fall back to trunk duplicate fields for physical_product when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'duplicate',
					undefined,
					'is_duplicate',
					'physical_product'
				);

				// Should fall back to trunk duplicate fields since no matrix entry for physical_product
				expect( result ).toHaveLength( 6 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
			} );

			it( 'should fall back to trunk subscription_canceled fields for physical_product when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'physical_product'
				);

				// Should fall back to trunk subscription_canceled fields since no matrix entry for physical_product
				expect( result ).toHaveLength( 6 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 2 ].label ).toBe(
					'Proof of active subscription'
				);
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
			} );

			it( 'should fall back to trunk subscription_canceled fields for digital_product_or_service when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'digital_product_or_service'
				);

				// Should fall back to trunk subscription_canceled fields since no matrix entry for digital_product_or_service
				expect( result ).toHaveLength( 6 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
			} );

			it( 'should fall back to trunk subscription_canceled fields for booking_reservation when feature flag is enabled', () => {
				global.wcpaySettings.featureFlags.isDisputeAdditionalEvidenceTypesEnabled = true;

				const result = getRecommendedDocumentFields(
					'subscription_canceled',
					undefined,
					undefined,
					'booking_reservation'
				);

				// Should fall back to trunk subscription_canceled fields since no matrix entry for booking_reservation
				expect( result ).toHaveLength( 6 );
				expect( result[ 0 ].key ).toBe( 'receipt' );
				expect( result[ 1 ].key ).toBe( 'customer_communication' );
				expect( result[ 2 ].key ).toBe( 'access_activity_log' );
				expect( result[ 3 ].key ).toBe( 'refund_policy' );
				expect( result[ 4 ].key ).toBe( 'cancellation_policy' );
				expect( result[ 5 ].key ).toBe( 'uncategorized_file' );
			} );
		} );
	} );
} );
