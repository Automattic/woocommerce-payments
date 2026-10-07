/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Pill from '../pill';
import { HoverTooltip } from 'wcpay/components/tooltip';
import './style.scss';

interface Props {
	earlyFraudWarning?: {
		actionable: boolean;
		fraud_type: string;
	};
}

const EarlyFraudWarningPill: React.FC< Props > = ( { earlyFraudWarning } ) => {
	// Resolved warnings (refunded or disputed) are noise in a discovery
	// surface — the details screens cover the historical state.
	if ( ! earlyFraudWarning?.actionable ) {
		return null;
	}

	return (
		<HoverTooltip
			content={ __(
				"The cardholder's bank reported this payment as potentially fraudulent. Refunding it now can prevent a dispute.",
				'woocommerce-payments'
			) }
		>
			<Pill className="early-fraud-warning-pill" type="alert">
				{ __( 'Fraud warning', 'woocommerce-payments' ) }
			</Pill>
		</HoverTooltip>
	);
};

export default EarlyFraudWarningPill;
