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

// Single "Learn more" destination for the "What could help next time"
// section, per RiskOps review (per-rec action links were too noisy). Same
// target as Cluster 15's link by design: a merchant looking at this section
// needs the dispute-response docs, not the prevention ones.
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
	<article
		key={ rec.id }
		className={ `dispute-recommendations__item dispute-recommendations__item--${ rec.urgency }` }
	>
		{ /* Two shapes: `published` (check) for strengths, `caution` (!) for
		     coaching. Color via the --{urgency} modifier (green / amber, no
		     red), routed through currentColor. See style.scss. Design 2026-05-27. */ }
		<span className="dispute-recommendations__icon" aria-hidden="true">
			<Icon
				icon={ rec.urgency === 'positive' ? published : caution }
				size={ 18 }
			/>
		</span>
		<div className="dispute-recommendations__text">
			<h4 className="dispute-recommendations__title">
				<VisuallyHidden>
					{ urgencyLabel( rec.urgency ) + ' ' }
				</VisuallyHidden>
				{ rec.title }
			</h4>
			<p className="dispute-recommendations__body">{ rec.body }</p>
		</div>
	</article>
);

// Both sections sit inside ONE expanded-by-default AccordionBody (single
// chevron), reusing the "Steps you can take" accordion. Per the 2026-05-27
// design decision, the won-strengths and coaching framings can both show
// regardless of outcome, driven by which recs fire. Section headings are h3
// sub-headings inside the body rather than the accordion header.
const renderSection = (
	heading: string,
	items: Recommendation[],
	learnMoreHref?: string
): JSX.Element | null => {
	if ( items.length === 0 ) {
		return null;
	}

	const sorted = [ ...items ].sort( sortByLift );
	const visible = sorted.slice( 0, VISIBLE_PER_SECTION );
	const hidden = sorted.slice( VISIBLE_PER_SECTION );

	return (
		<section className="dispute-recommendations-card__section">
			<div className="dispute-recommendations-card__section-header">
				<h3 className="dispute-recommendations-card__section-heading">
					{ heading }
				</h3>
				{ learnMoreHref && (
					<ExternalLink
						className="dispute-recommendations-card__learn-more"
						href={ learnMoreHref }
					>
						{ __( 'Learn more', 'woocommerce-payments' ) }
						{ /* SR-only context. Setting `aria-label` would
						     override the whole accessible name and drop
						     ExternalLink's built-in "(opens in a new tab)"
						     suffix; adding a VisuallyHidden child instead
						     keeps both. */ }
						<VisuallyHidden>
							{ ' ' +
								__(
									'about managing payment disputes',
									'woocommerce-payments'
								) }
						</VisuallyHidden>
					</ExternalLink>
				) }
			</div>
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
		</section>
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
		<Accordion defaultExpanded className="dispute-recommendations-card">
			<AccordionBody
				title={ __( 'Recommendations', 'woocommerce-payments' ) }
				lg
			>
				{ renderSection(
					__( "What's working well", 'woocommerce-payments' ),
					positives
				) }
				{ renderSection(
					__( 'What could help next time', 'woocommerce-payments' ),
					criticalsAndTips,
					LEARN_MORE_HREF
				) }
			</AccordionBody>
		</Accordion>
	);
};

export default DisputeRecommendationsCard;
