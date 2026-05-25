/** @format **/

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import EvidenceSubmittedList from './evidence-submitted-list';
import { recordOutcomeViewOnce } from './tracks';
import './style.scss';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

const DisputeOutcomeView: React.FC< DisputeOutcomeViewProps > = ( {
	dispute,
} ) => {
	// Computed once so the Tracks payload and the matrix UI use the same result.
	const productType = resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	);

	useEffect( () => {
		recordOutcomeViewOnce( dispute, productType );
	}, [ dispute, productType ] );

	const fields = getExpectedFieldStatus(
		dispute.reason,
		productType,
		dispute.evidence
	);
	// On a successful outcome the optional rows are non-actionable noise
	// next to the wins, so collapse them behind a disclosure. On lost they
	// stay inline as helpful "what was missing" context.
	const collapseOptionalMissing =
		dispute.status === 'won' || dispute.status === 'warning_closed';

	return (
		<section className="dispute-outcome-view">
			<CardDivider />
			<h3 className="dispute-outcome-view__section-heading">
				{ __( 'Evidence Submitted', 'woocommerce-payments' ) }
			</h3>
			<EvidenceSubmittedList
				fields={ fields }
				collapseOptionalMissing={ collapseOptionalMissing }
			/>
		</section>
	);
};

export default DisputeOutcomeView;
