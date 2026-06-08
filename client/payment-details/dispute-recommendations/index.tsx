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

// Higher lift first; unmeasured entries fall to the bottom in catalog order.
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

// SR-only severity qualifier; the icon is aria-hidden, so sighted-only cues
// need a textual equivalent.
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
	// Reuses the shared row from "Steps you can take". Urgency BEM hooks on
	// the root drive the icon tint via style.scss; the shared component
	// stays urgency-agnostic.
	<DisputeStepItem
		key={ rec.id }
		as="article"
		// h3 keeps the outline monotonic under the card's h2 title.
		titleAs="h3"
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

// Both cards route their description through `subtitleNode` so the closed
// state stays consistent: using `subtitle` for one and `subtitleNode` for
// the other diverges the layout.
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

	const subtitleNode = (
		<>
			{ description }
			{ learnMoreHref && (
				<>
					{ ' ' }
					<ExternalLink href={ learnMoreHref }>
						{ __( 'Learn more', 'woocommerce-payments' ) }
						{ /* SR-only context. Setting aria-label would override
						     the whole accessible name and drop ExternalLink's
						     built-in "(opens in a new tab)" suffix; a
						     VisuallyHidden child keeps both. */ }
						<VisuallyHidden>
							{ ' ' +
								__(
									'about managing payment disputes',
									'woocommerce-payments'
								) }
						</VisuallyHidden>
					</ExternalLink>
				</>
			) }
		</>
	);

	return (
		<Accordion className="dispute-recommendations-card">
			<AccordionBody title={ heading } subtitleNode={ subtitleNode } lg>
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

	// COUPLED with summary/index.tsx: the Tracks event records this same
	// productType. Keep both call sites in lockstep.
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
