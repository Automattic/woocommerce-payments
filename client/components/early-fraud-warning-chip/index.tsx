/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import './style.scss';

interface Props {
	earlyFraudWarning?: {
		actionable: boolean;
		fraud_type: string;
	};
}

const EarlyFraudWarningChip: React.FC< Props > = ( { earlyFraudWarning } ) => {
	// Resolved warnings (refunded or disputed) are noise in a discovery
	// surface — the details screens cover the historical state.
	if ( ! earlyFraudWarning?.actionable ) {
		return null;
	}

	return (
		<Chip
			className="early-fraud-warning-chip"
			message={ __( 'Fraud warning', 'woocommerce-payments' ) }
			type="warning"
			tooltip={ __(
				"The cardholder's bank reported this payment as potentially fraudulent. Refunding it now can prevent a dispute.",
				'woocommerce-payments'
			) }
		/>
	);
};

export default EarlyFraudWarningChip;
