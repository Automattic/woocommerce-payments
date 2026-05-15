/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	ExternalLink,
	VisuallyHidden,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
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

// Single "Learn more" destination next to the "What could help next time"
// heading, per RiskOps review (per-rec action links were too noisy alongside
// the Evidence Submitted card above). Same target as Cluster 15's link by
// design: a merchant looking at this section is staring at a lost dispute
// and needs the dispute-response docs, not the prevention ones.
// eslint-disable-next-line @typescript-eslint/naming-convention -- module-level URL constant
const LEARN_MORE_HREF =
	'https://woocommerce.com/document/managing-payment-disputes/';

// Map dispute status to the outcome framing used for catalog matching.
// warning_closed has no entry: inquiries carry no merchant-submitted
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

// Urgency icons sit before each rec title. SVGs use `stroke="currentColor"`
// so the color is controlled by the parent `.dispute-recommendations__icon`
// class via the urgency modifier (see style.scss). Per RiskOps review: color
// lives in the icon, not in the title, to align with WooPayments admin's
// restrained color vocabulary.
const urgencyIcons: Record< RecommendationUrgency, JSX.Element > = {
	positive: (
		<svg
			width="18"
			height="18"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			<circle cx="12" cy="12" r="9" />
			<path d="m9 12 2 2 4-4" />
		</svg>
	),
	critical: (
		<svg
			width="18"
			height="18"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			<path d="M12 9v4" />
			<path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" />
			<path d="M12 16h.01" />
		</svg>
	),
	tip: (
		<svg
			width="18"
			height="18"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 8v4" />
			<path d="M12 16h.01" />
		</svg>
	),
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
		<span className="dispute-recommendations__icon" aria-hidden="true">
			{ urgencyIcons[ rec.urgency ] }
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
								'Show %d more',
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
		<Card className="dispute-recommendations-card">
			<CardBody>
				{ renderSection(
					__( "What's working well", 'woocommerce-payments' ),
					positives
				) }
				{ renderSection(
					__( 'What could help next time', 'woocommerce-payments' ),
					criticalsAndTips,
					LEARN_MORE_HREF
				) }
			</CardBody>
		</Card>
	);
};

export default DisputeRecommendationsCard;
