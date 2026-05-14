/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, ExternalLink } from '@wordpress/components';
import { Link } from '@woocommerce/components';

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
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import './style.scss';

interface Props {
	dispute: ChargeDispute;
}

// eslint-disable-next-line @typescript-eslint/naming-convention -- module-level numeric constant
const VISIBLE_PER_SECTION = 3;

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

// Catalog links are either absolute external URLs (woocommerce.com docs)
// or wp-admin destinations built via getAdminUrl()/addQueryArgs(). Only the
// former should render as an ExternalLink (new tab + external affordance);
// wp-admin links use the standard internal Link to stay in the admin.
const isExternalHref = ( href: string ): boolean => /^https?:\/\//.test( href );

const renderLink = ( link: Recommendation[ 'link' ] ): JSX.Element | null => {
	if ( ! link ) {
		return null;
	}
	if ( isExternalHref( link.href ) ) {
		return (
			<ExternalLink
				className="dispute-recommendations__link"
				href={ link.href }
			>
				{ link.label }
			</ExternalLink>
		);
	}
	return (
		<Link
			className="dispute-recommendations__link"
			href={ link.href }
			type="wp-admin"
		>
			{ link.label }
		</Link>
	);
};

const renderItem = ( rec: Recommendation ): JSX.Element => (
	<article
		key={ rec.id }
		className={ `dispute-recommendations__item dispute-recommendations__item--${ rec.urgency }` }
	>
		<h4 className="dispute-recommendations__title">{ rec.title }</h4>
		<p className="dispute-recommendations__body">{ rec.body }</p>
		{ renderLink( rec.link ) }
	</article>
);

const renderSection = (
	heading: string,
	items: Recommendation[]
): JSX.Element | null => {
	if ( items.length === 0 ) {
		return null;
	}

	const sorted = [ ...items ].sort( sortByLift );
	const visible = sorted.slice( 0, VISIBLE_PER_SECTION );
	const hidden = sorted.slice( VISIBLE_PER_SECTION );

	return (
		<section className="dispute-recommendations-card__section">
			<h3 className="dispute-recommendations-card__section-heading">
				{ heading }
			</h3>
			{ visible.map( renderItem ) }
			{ hidden.length > 0 && (
				<details className="dispute-recommendations-card__show-more">
					<summary>
						{ sprintf(
							/* translators: %d is the number of additional recommendations hidden by default. */
							__( 'Show %d more', 'woocommerce-payments' ),
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
					criticalsAndTips
				) }
			</CardBody>
		</Card>
	);
};

export default DisputeRecommendationsCard;
