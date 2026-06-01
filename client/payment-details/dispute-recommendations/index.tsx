/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ExternalLink, VisuallyHidden } from '@wordpress/components';
import { Icon, caution, published } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { Accordion, AccordionBody } from 'wcpay/components/accordion';
import DisputeStepItem from 'wcpay/components/dispute-step-item';
import type { ChargeDispute } from 'wcpay/types/charges';
import type {
	Recommendation,
	RecommendationOutcome,
	RecommendationUrgency,
} from 'wcpay/disputes/new-evidence/types';
import { getRecommendations } from 'wcpay/disputes/new-evidence/recommendations';
import { RECOMMENDATIONS_CATALOG } from 'wcpay/disputes/new-evidence/recommendation-catalog';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import './style.scss';

interface Props {
	dispute: ChargeDispute;
}

// eslint-disable-next-line @typescript-eslint/naming-convention -- module-level numeric constant
const VISIBLE_PER_SECTION = 3;

// Single "Learn more" destination for the coaching card, per RiskOps review.
// eslint-disable-next-line @typescript-eslint/naming-convention -- module-level URL constant
const LEARN_MORE_HREF =
	'https://woocommerce.com/document/managing-payment-disputes/';

// Map dispute status to the outcome framing used for catalog matching.
// warning_* statuses have no entry: inquiries carry no merchant-submitted
// evidence, so neither outcome's recommendations have a behavioral hook.
const outcomeByStatus: Partial<
	Record< ChargeDispute[ 'status' ], RecommendationOutcome >
> = {
	lost: 'could_help',
	won: 'keep_doing',
};

// Higher lift first; entries without a measured lift sort to the bottom.
// Ties (including both-unmeasured) return 0, so the stable sort preserves
// catalog order within a bucket.
const sortByLift = ( a: Recommendation, b: Recommendation ): number => {
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

// Severity qualifiers for screen readers. Sighted users get severity from
// the icon shape and color; SR users need a textual cue since the icon is
// aria-hidden.
const urgencyLabel = ( urgency: RecommendationUrgency ): string => {
	switch ( urgency ) {
		case 'critical':
			// translators: SR-only label preceding a critical recommendation title.
			return __( 'Important:', 'woocommerce-payments' );
		case 'tip':
			// translators: SR-only label preceding a tip recommendation title.
			return __( 'Tip:', 'woocommerce-payments' );
		case 'positive':
		default:
			// translators: SR-only label preceding a positive recommendation title.
			return __( 'Working well:', 'woocommerce-payments' );
	}
};

const renderItem = ( rec: Recommendation ): JSX.Element => (
	// Reuses the shared `DisputeStepItem` row from "Steps you can take" so the
	// geometry (44x44 bordered icon, 16px padding, gray-100 hairline, mobile
	// collapse) stays in lockstep with that pattern. The urgency BEM hooks
	// `dispute-recommendations__item--{urgency}` ride on the root and drive
	// the icon tint in `style.scss`; the shared component itself stays
	// urgency-agnostic.
	<DisputeStepItem
		key={ rec.id }
		as="article"
		titleAs="h4"
		className={ `dispute-recommendations__item dispute-recommendations__item--${ rec.urgency }` }
		icon={
			<Icon
				icon={ rec.urgency === 'positive' ? published : caution }
				size={ 24 }
			/>
		}
		titleSrPrefix={ urgencyLabel( rec.urgency ) }
		title={ rec.title }
		description={ rec.body }
	/>
);

// Each non-empty section renders as its own expanded-by-default Accordion
// card (Lucy review 2026-05-29 — separate cards, not a shared shell). The
// description sits in the AccordionBody's subtitle slot so it hugs the
// heading the way "Steps you can take" does. The coaching card passes its
// "Learn more" link via `subtitleNode`, which renders the subtitle as a
// sibling to the toggle button so the inline <a> stays out of the button's
// accessible name (Lucy review 2026-06-01).
const renderCard = (
	heading: string,
	description: string,
	items: Recommendation[],
	learnMoreHref?: string
): JSX.Element | null => {
	if ( items.length === 0 ) {
		return null;
	}

	const sorted = [ ...items ].sort( sortByLift );
	const visible = sorted.slice( 0, VISIBLE_PER_SECTION );
	const hidden = sorted.slice( VISIBLE_PER_SECTION );

	const subtitleNode = learnMoreHref ? (
		<>
			{ description }{ ' ' }
			<ExternalLink href={ learnMoreHref }>
				{ __( 'Learn more', 'woocommerce-payments' ) }
				{ /* SR-only context. Setting aria-label would override the
				     whole accessible name and drop ExternalLink's built-in
				     "(opens in a new tab)" suffix; a VisuallyHidden child
				     keeps both. */ }
				<VisuallyHidden>
					{ ' ' +
						__(
							'about managing payment disputes',
							'woocommerce-payments'
						) }
				</VisuallyHidden>
			</ExternalLink>
		</>
	) : undefined;

	return (
		<Accordion defaultExpanded className="dispute-recommendations-card">
			<AccordionBody
				title={ heading }
				// Use `subtitle` (string, inside the toggle button) when there
				// is no link to embed; use `subtitleNode` (rich, sibling of
				// the toggle) when the description carries an inline link.
				subtitle={ subtitleNode ? undefined : description }
				subtitleNode={ subtitleNode }
				lg
			>
				{ visible.map( renderItem ) }
				{ hidden.length > 0 && (
					<details className="dispute-recommendations-card__show-more">
						<summary>
							{ sprintf(
								/* translators: %d is the number of additional recommendations hidden by default. */
								_n(
									'Show 1 more',
									'Show %d more',
									hidden.length,
									'woocommerce-payments'
								),
								hidden.length
							) }
						</summary>
						{ hidden.map( renderItem ) }
					</details>
				) }
			</AccordionBody>
		</Accordion>
	);
};

const DisputeRecommendationsCard: React.FC< Props > = ( { dispute } ) => {
	const outcome = outcomeByStatus[ dispute.status ];
	if ( ! outcome ) {
		return null;
	}

	const productType = resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	);

	const recommendations = getRecommendations(
		{
			reason: dispute.reason,
			productType,
			outcome,
			evidence: dispute.evidence,
		},
		RECOMMENDATIONS_CATALOG
	);

	if ( recommendations.length === 0 ) {
		return null;
	}

	const positives = recommendations.filter(
		( r ) => r.urgency === 'positive'
	);
	const criticalsAndTips = recommendations.filter(
		( r ) => r.urgency !== 'positive'
	);

	return (
		<>
			{ renderCard(
				__( "What's working well", 'woocommerce-payments' ),
				__(
					'These are the evidence strengths that supported your dispute response.',
					'woocommerce-payments'
				),
				positives
			) }
			{ renderCard(
				__( 'What could help next time', 'woocommerce-payments' ),
				__(
					'Strengthen future dispute responses by adding these details to your evidence before submitting.',
					'woocommerce-payments'
				),
				criticalsAndTips,
				LEARN_MORE_HREF
			) }
		</>
	);
};

export default DisputeRecommendationsCard;
