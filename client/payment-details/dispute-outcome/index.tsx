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
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import EvidenceSubmittedList from './evidence-submitted-list';
import './style.scss';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

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

	return (
		<section className="dispute-outcome-view">
			<CardDivider />
			<h3 className="dispute-outcome-view__section-heading">
				{ __( 'Evidence Submitted', 'woocommerce-payments' ) }
			</h3>
			<EvidenceSubmittedList fields={ fields } />
		</section>
	);
};

export default DisputeOutcomeView;
