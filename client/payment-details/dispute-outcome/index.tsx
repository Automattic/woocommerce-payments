/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import EvidenceSubmittedList from './evidence-submitted-list';
import './style.scss';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

// Reads the same two sources the pre-response wizard reads from. The wizard
// writes its selection back to `metadata.__product_type` on save, so resolved
// disputes that went through the wizard have it populated; older disputes
// fall back to `order.suggested_product_type`. Either may be empty, in which
// case `getExpectedFieldStatus` returns no high-impact/topical rows.
const resolveProductType = ( dispute: ChargeDispute ): string =>
	dispute.metadata?.__product_type ||
	dispute.order?.suggested_product_type ||
	'';

const DisputeOutcomeView: React.FC< DisputeOutcomeViewProps > = ( {
	dispute,
} ) => {
	const fields = getExpectedFieldStatus(
		dispute.reason,
		resolveProductType( dispute ),
		dispute.evidence
	);

	return (
		<section className="dispute-outcome-view">
			<h3 className="dispute-outcome-view__section-heading">
				{ __( 'Evidence Submitted', 'woocommerce-payments' ) }
			</h3>
			<EvidenceSubmittedList fields={ fields } />
		</section>
	);
};

export default DisputeOutcomeView;
