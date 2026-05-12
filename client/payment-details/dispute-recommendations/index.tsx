/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import type { RecommendationOutcome } from 'wcpay/disputes/new-evidence/types';
import { getRecommendations } from 'wcpay/disputes/new-evidence/recommendations';
import { RECOMMENDATIONS_CATALOG } from 'wcpay/disputes/new-evidence/recommendation-catalog';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import './style.scss';

interface Props {
	dispute: ChargeDispute;
}

// warning_closed has no entry: inquiries never carry merchant-submitted
// evidence, so neither framing has a behavioral hook.
const outcomeByStatus: Partial<
	Record< ChargeDispute[ 'status' ], RecommendationOutcome >
> = {
	lost: 'could_help',
	won: 'keep_doing',
};

const cardHeadings: Record< RecommendationOutcome, string > = {
	could_help: __(
		'What could help in future disputes',
		'woocommerce-payments'
	),
	keep_doing: __( 'What to keep doing', 'woocommerce-payments' ),
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

	return (
		<Card className="dispute-recommendations-card">
			<CardHeader>
				<h3 className="dispute-recommendations-card__heading">
					{ cardHeadings[ outcome ] }
				</h3>
			</CardHeader>
			<CardBody>
				{ recommendations.map( ( rec ) => (
					<article
						key={ rec.id }
						className={ `dispute-recommendations__item dispute-recommendations__item--${ rec.urgency }` }
					>
						<h4 className="dispute-recommendations__title">
							{ rec.title }
						</h4>
						<p className="dispute-recommendations__body">
							{ rec.body }
						</p>
						{ rec.link && (
							<ExternalLink
								className="dispute-recommendations__link"
								href={ rec.link.href }
							>
								{ rec.link.label }
							</ExternalLink>
						) }
					</article>
				) ) }
			</CardBody>
		</Card>
	);
};

export default DisputeRecommendationsCard;
