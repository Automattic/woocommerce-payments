/** @format **/

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import type {
	Recommendation,
	RecommendationOutcome,
} from 'wcpay/disputes/new-evidence/types';
import { getRecommendations } from 'wcpay/disputes/new-evidence/recommendations';
import { RECOMMENDATIONS_CATALOG } from 'wcpay/disputes/new-evidence/recommendation-catalog';

// Map dispute status to the outcome framing used for catalog matching.
// warning_* statuses have no entry: inquiries carry no merchant-submitted
// evidence, so neither outcome's recommendations have a behavioral hook.
const outcomeByStatus: Partial<
	Record< ChargeDispute[ 'status' ], RecommendationOutcome >
> = {
	lost: 'could_help',
	won: 'keep_doing',
};

/**
 * Recommendations for a resolved dispute, or `[]` for a status with no outcome
 * (e.g. warning_closed). Shared with the wrapper so its `has_recommendations`
 * flag can't disagree with what the card renders.
 */
export const getDisputeRecommendations = (
	dispute: ChargeDispute,
	productType: string
): Recommendation[] => {
	const outcome = outcomeByStatus[ dispute.status ];
	if ( ! outcome ) {
		return [];
	}
	return getRecommendations(
		{
			reason: dispute.reason,
			productType,
			outcome,
			evidence: dispute.evidence,
		},
		RECOMMENDATIONS_CATALOG
	);
};

// Higher lift first; unmeasured entries fall to the bottom in catalog order.
export const sortByLift = ( a: Recommendation, b: Recommendation ): number => {
	if ( typeof a.lift !== 'number' && typeof b.lift !== 'number' ) {
		return 0;
	}
	if ( typeof a.lift !== 'number' ) {
		return 1;
	}
	if ( typeof b.lift !== 'number' ) {
		return -1;
	}
	return b.lift - a.lift;
};
