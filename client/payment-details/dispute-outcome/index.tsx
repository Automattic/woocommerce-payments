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
	// `resolve-product-type.ts` reads metadata first (wizard-written) then
	// the order's suggested type, and normalizes legacy `multiple` to `other`
	// when the additional-evidence-types flag is on, so this view looks up
	// the same matrix cell the wizard wrote against. Either source may be
	// empty, in which case `getExpectedFieldStatus` returns no rows.
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
