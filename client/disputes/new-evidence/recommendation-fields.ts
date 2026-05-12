/**
 * Internal dependencies
 */
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';
import type { EvidenceFieldStatus } from './types';
import { getExpectedFieldStatus } from './evidence-field-status';
import { DISPUTE_HIGH_IMPACT_FIELDS } from './constants/high-impact-fields';

export type RecommendationOutcome = 'could_help' | 'keep_doing';

/**
 * Returns the subset of `EvidenceFieldStatus` items that the post-resolution
 * recommendations section should surface for the given outcome.
 *
 *   - `could_help` (lost) → fields the merchant didn't supply that would
 *     likely have strengthened the response: `expected_missing` only.
 *   - `keep_doing` (won)  → fields the merchant supplied that are in the
 *     high-impact map for the (reason × productType) cell: `provided` items
 *     filtered to high-impact keys.
 *
 * Composes `getExpectedFieldStatus` rather than touching the matrix or
 * evidence directly, so any future field-status changes apply automatically.
 */
export const getRecommendationFields = (
	reason: string,
	productType: string,
	evidence: Record< string, unknown >,
	outcome: RecommendationOutcome
): EvidenceFieldStatus[] => {
	const fields = getExpectedFieldStatus( reason, productType, evidence );

	if ( outcome === 'could_help' ) {
		return fields.filter( ( field ) => field.state === 'expected_missing' );
	}

	const highImpactKeys = new Set(
		DISPUTE_HIGH_IMPACT_FIELDS[ reason as DisputeReason ]?.[
			productType as ProductType
		] ?? []
	);
	return fields.filter(
		( field ) =>
			field.state === 'provided' && highImpactKeys.has( field.key )
	);
};
