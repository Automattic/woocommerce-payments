/** @format **/

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import type { ChargeDispute } from 'wcpay/types/charges';

// Stable Tracks key, not the translated heading: survives copy/locale changes.
export type DisputeOutcomeSection = 'whats_working_well' | 'what_could_help';

// Add new interactions here rather than minting a new event name.
export type DisputeOutcomeAction = 'learn_more_clicked' | 'show_more_expanded';

const seenDisputeIds = new Set< string >();
const seenSectionKeys = new Set< string >();

/**
 * Base properties every Outcome View event shares. productType is passed in
 * (not resolved here) so it can't drift from what the caller renders, and is
 * omitted when unresolved so analytics never see a '' bucket.
 */
export const getDisputeOutcomeTracksProperties = (
	dispute: ChargeDispute,
	productType: string | undefined
) => ( {
	dispute_id: dispute.id,
	dispute_status: dispute.status,
	dispute_reason: dispute.reason,
	...( productType ? { product_type: productType } : {} ),
} );

/**
 * Fires once per dispute per page session. De-dup is module-scoped, not a
 * per-instance ref: payment-details remounts the view several times per load,
 * so a ref would reset and re-fire.
 */
export const recordOutcomeViewOnce = (
	dispute: ChargeDispute,
	productType: string | undefined,
	hasRecommendations: boolean
): void => {
	if ( ! dispute.id || seenDisputeIds.has( dispute.id ) ) {
		return;
	}
	recordEvent( 'wcpay_dispute_outcome_viewed', {
		...getDisputeOutcomeTracksProperties( dispute, productType ),
		has_recommendations: hasRecommendations,
	} );
	seenDisputeIds.add( dispute.id );
};

/**
 * Fires once per dispute per section per page session. recommendationIds
 * arrive in render (sorted-by-lift) order, matching what the merchant saw.
 */
export const recordSectionViewedOnce = (
	dispute: ChargeDispute,
	productType: string | undefined,
	section: DisputeOutcomeSection,
	recommendationIds: string[],
	visibleCount: number
): void => {
	const key = `${ dispute.id }:${ section }`;
	if ( ! dispute.id || seenSectionKeys.has( key ) ) {
		return;
	}
	recordEvent( 'wcpay_dispute_outcome_recommendations_section_viewed', {
		...getDisputeOutcomeTracksProperties( dispute, productType ),
		section,
		recommendation_count: recommendationIds.length,
		visible_count: visibleCount,
		recommendation_ids: recommendationIds,
	} );
	seenSectionKeys.add( key );
};

/**
 * No de-dup: clicks and expands are discrete actions, and a repeat is real
 * signal. Toggle callers must fire on expand only (check the open state).
 */
export const recordOutcomeAction = (
	dispute: ChargeDispute,
	productType: string | undefined,
	props: {
		action: DisputeOutcomeAction;
		section?: DisputeOutcomeSection;
		linkHref?: string;
	}
): void => {
	if ( ! dispute.id ) {
		return;
	}
	recordEvent( 'wcpay_dispute_outcome_action_clicked', {
		...getDisputeOutcomeTracksProperties( dispute, productType ),
		action: props.action,
		...( props.section ? { section: props.section } : {} ),
		...( props.linkHref ? { link_href: props.linkHref } : {} ),
	} );
};

/**
 * Test-only: clears the de-dup memory.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
export const _resetOutcomeViewTrackingForTests = (): void => {
	seenDisputeIds.clear();
	seenSectionKeys.clear();
};
