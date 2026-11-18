/**
 * Internal dependencies
 */
import { resolveEvidenceStatuses } from '../resolve-evidence-status';

declare const global: {
	wcpaySettings: {
		featureFlags: { isDisputeAdditionalEvidenceTypesEnabled: boolean };
	};
};

const physicalProduct = 'physical_product';
// Files in the slots only the "Refund was not owed" layout has.
const notOwedEvidence = {
	uncategorized_file: 'file_proof_of_acceptance',
	service_documentation: 'file_other_documents',
};
// Fit both duplicate layouts: "It was not a duplicate" has a subset of the "It was a duplicate" slots.
const sharedDuplicateEvidence = {
	receipt: 'file_receipt',
	refund_policy: 'file_policy',
};

describe( 'resolveEvidenceStatuses', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			featureFlags: { isDisputeAdditionalEvidenceTypesEnabled: true },
		};
	} );

	it( 'restores a saved refund status', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'credit_not_processed',
					metadata: { __refund_status: 'refund_was_not_owed' },
				},
				physicalProduct
			).refundStatus
		).toBe( 'refund_was_not_owed' );
	} );

	it( 'prefers the saved refund status over the saved documents', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'credit_not_processed',
					metadata: { __refund_status: 'refund_has_been_issued' },
					evidence: notOwedEvidence,
				},
				physicalProduct
			).refundStatus
		).toBe( 'refund_has_been_issued' );
	} );

	it( 'restores a saved duplicate status', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'duplicate',
					metadata: { __duplicate_status: 'is_not_duplicate' },
				},
				physicalProduct
			).duplicateStatus
		).toBe( 'is_not_duplicate' );
	} );

	it( 'ignores an unknown saved status', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'credit_not_processed',
					metadata: { __refund_status: 'unknown' },
				},
				physicalProduct
			).refundStatus
		).toBe( 'refund_has_been_issued' );
	} );

	it( 'defaults when nothing is saved', () => {
		expect(
			resolveEvidenceStatuses(
				{ reason: 'credit_not_processed', metadata: {}, evidence: {} },
				physicalProduct
			)
		).toEqual( {
			refundStatus: 'refund_has_been_issued',
			duplicateStatus: 'is_duplicate',
		} );
	} );

	it( 'picks the refund layout that shows every saved document when no status is saved', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'credit_not_processed',
					metadata: null,
					evidence: notOwedEvidence,
				},
				physicalProduct
			).refundStatus
		).toBe( 'refund_was_not_owed' );
	} );

	it( 'keeps the default duplicate layout when it shows every saved document', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'duplicate',
					metadata: {},
					evidence: sharedDuplicateEvidence,
				},
				physicalProduct
			).duplicateStatus
		).toBe( 'is_duplicate' );
	} );

	it( 'keeps the default when no layout shows every saved document', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'credit_not_processed',
					metadata: {},
					evidence: {
						...notOwedEvidence,
						receipt: 'file_receipt',
					},
				},
				physicalProduct
			).refundStatus
		).toBe( 'refund_has_been_issued' );
	} );

	it( 'ignores the saved statuses of other dispute reasons', () => {
		expect(
			resolveEvidenceStatuses(
				{
					reason: 'fraudulent',
					metadata: {
						__refund_status: 'refund_was_not_owed',
						__duplicate_status: 'is_not_duplicate',
					},
				},
				physicalProduct
			)
		).toEqual( {
			refundStatus: 'refund_has_been_issued',
			duplicateStatus: 'is_duplicate',
		} );
	} );
} );
