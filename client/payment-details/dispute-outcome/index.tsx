/** @format **/

/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import type { ChargeDispute } from 'wcpay/types/charges';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import { resolveProductType } from 'wcpay/disputes/new-evidence/resolve-product-type';
import EvidenceSubmittedList from './evidence-submitted-list';
import { getDisputeOutcomeTracksProperties } from './tracks';
import './style.scss';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

const DisputeOutcomeView: React.FC< DisputeOutcomeViewProps > = ( {
	dispute,
} ) => {
	// Resolve once and share with the Tracks payload below, so the matrix
	// cell the merchant sees and the `product_type` we record can't drift.
	// Mirrors the wizard's resolution.
	const productType = resolveProductType(
		dispute.metadata,
		dispute.order?.suggested_product_type,
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ??
			false
	);

	// Track the last dispute.id we fired a view event for, rather than a
	// simple "viewed" boolean. This handles two cases at once: React 18
	// Strict Mode's dev-only double-invocation of effects (same id, skip),
	// and the SPA case where the parent updates the `dispute` prop in place
	// without remounting between transactions (different id, fire).
	const lastTrackedDisputeIdRef = useRef< string | null >( null );
	useEffect( () => {
		if ( lastTrackedDisputeIdRef.current === dispute.id ) {
			return;
		}
		recordEvent(
			'wcpay_dispute_outcome_viewed',
			getDisputeOutcomeTracksProperties( dispute, productType )
		);
		lastTrackedDisputeIdRef.current = dispute.id;
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
