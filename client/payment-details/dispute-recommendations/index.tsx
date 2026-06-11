/** @format **/

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
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
	RecommendationUrgency,
} from 'wcpay/disputes/new-evidence/types';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import {
	recordSectionViewedOnce,
	recordOutcomeAction,
} from '../dispute-outcome/tracks';
import type { DisputeOutcomeSection } from '../dispute-outcome/tracks';
import { getDisputeRecommendations, sortByLift } from './utils';
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

// Shared by render and the section-view event so they split sections alike.
const isPositive = ( rec: Recommendation ): boolean =>
	rec.urgency === 'positive';

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

interface RecommendationSectionProps {
	dispute: ChargeDispute;
	productType: string;
	section: DisputeOutcomeSection;
	heading: string;
	description: string;
	items: Recommendation[];
	learnMoreHref?: string;
}

// One outcome section (renders nothing when it has no entries). The description
// goes through `subtitleNode` so the collapsed state stays consistent whether
// or not the section carries a "Learn more" link.
const RecommendationSection: React.FC< RecommendationSectionProps > = ( {
	dispute,
	productType,
	section,
	heading,
	description,
	items,
	learnMoreHref,
} ) => {
	const sorted = [ ...items ].sort( sortByLift );
	const visible = sorted.slice( 0, VISIBLE_PER_SECTION );
	const hidden = sorted.slice( VISIBLE_PER_SECTION );

	// Fire the section-viewed event once per non-empty section, sourced from the
	// same `sorted`/`visible` the card renders so recommendation_ids can't drift
	// from render order. The module-scoped de-dup absorbs payment-details remounts.
	useEffect( () => {
		if ( sorted.length === 0 ) {
			return;
		}
		recordSectionViewedOnce(
			dispute,
			productType,
			section,
			sorted.map( ( rec ) => rec.id ),
			visible.length
		);
	}, [ dispute, productType, section, sorted, visible ] );

	if ( items.length === 0 ) {
		return null;
	}

	const subtitleNode = (
		<>
			{ description }
			{ learnMoreHref && (
				<>
					{ ' ' }
					<ExternalLink
						href={ learnMoreHref }
						onClick={ () =>
							recordOutcomeAction( dispute, productType, {
								action: 'learn_more_clicked',
								section,
								linkHref: learnMoreHref,
							} )
						}
					>
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
					<details
						className="dispute-recommendations-card__show-more"
						onToggle={ ( event ) => {
							// Expand only; a collapse is not engagement.
							if ( event.currentTarget.open ) {
								recordOutcomeAction( dispute, productType, {
									action: 'show_more_expanded',
									section,
								} );
							}
						} }
					>
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
	// COUPLED with summary/index.tsx: the wrapper resolves this same productType
	// for its has_recommendations flag. Keep in lockstep.
	const productType = resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	);

	const recommendations = getDisputeRecommendations( dispute, productType );

	if ( recommendations.length === 0 ) {
		return null;
	}

	const positives = recommendations.filter( isPositive );
	const criticalsAndTips = recommendations.filter(
		( r ) => ! isPositive( r )
	);

	return (
		<>
			<RecommendationSection
				dispute={ dispute }
				productType={ productType }
				section="whats_working_well"
				heading={ __( "What's working well", 'woocommerce-payments' ) }
				description={ __(
					'These are the evidence strengths that supported your dispute response.',
					'woocommerce-payments'
				) }
				items={ positives }
			/>
			<RecommendationSection
				dispute={ dispute }
				productType={ productType }
				section="what_could_help"
				heading={ __(
					'What could help next time',
					'woocommerce-payments'
				) }
				description={ __(
					'Strengthen future dispute responses by adding these details to your evidence before submitting.',
					'woocommerce-payments'
				) }
				items={ criticalsAndTips }
				learnMoreHref={ LEARN_MORE_HREF }
			/>
		</>
	);
};

export default DisputeRecommendationsCard;
