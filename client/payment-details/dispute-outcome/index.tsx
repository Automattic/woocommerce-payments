/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import {
	getRecommendationFields,
	type RecommendationOutcome,
} from 'wcpay/disputes/new-evidence/recommendation-fields';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import EvidenceSubmittedList from './evidence-submitted-list';
import RecommendationsList from './recommendations-list';
import './style.scss';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

// Maps the dispute outcome status to the recommendation framing.
// warning_closed has no entry: inquiries never carry merchant-submitted
// evidence, so "what could help" / "what to keep doing" has no behavioral
// hook to attach to.
const recommendationOutcomeByStatus: Partial<
	Record< ChargeDispute[ 'status' ], RecommendationOutcome >
> = {
	lost: 'could_help',
	won: 'keep_doing',
};

const DisputeOutcomeView: React.FC< DisputeOutcomeViewProps > = ( {
	dispute,
} ) => {
	// Mirror the wizard's resolution so both look up the same matrix cell.
	const productType = resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	);
	const fields = getExpectedFieldStatus(
		dispute.reason,
		productType,
		dispute.evidence
	);

	const recommendationOutcome =
		recommendationOutcomeByStatus[ dispute.status ];
	const recommendationFields = recommendationOutcome
		? getRecommendationFields(
				dispute.reason,
				productType,
				dispute.evidence,
				recommendationOutcome
		  )
		: [];

	return (
		<section className="dispute-outcome-view">
			<CardDivider />
			<h3 className="dispute-outcome-view__section-heading">
				{ __( 'Evidence Submitted', 'woocommerce-payments' ) }
			</h3>
			<EvidenceSubmittedList fields={ fields } />
			{ recommendationOutcome && (
				<RecommendationsList
					fields={ recommendationFields }
					outcome={ recommendationOutcome }
				/>
			) }
		</section>
	);
};

export default DisputeOutcomeView;
