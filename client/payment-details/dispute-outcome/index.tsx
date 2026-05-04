/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import type { ChargeDispute } from 'wcpay/types/charges';

interface DisputeOutcomeViewProps {
	dispute: ChargeDispute;
}

// Placeholder — returns null until subsequent PRs in the RSM Dispute
// Outcome View workstream wire up real content.
const DisputeOutcomeView: React.FC< DisputeOutcomeViewProps > = () => {
	return null;
};

export default DisputeOutcomeView;
