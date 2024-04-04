/**
 * External dependencies
 */
import * as React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { formatCurrency } from 'wcpay/utils/currency';

import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import './style.scss';

const TotalPaymentsVolume: React.FC = () => {
	const { accountDefaultCurrency } = wcpaySettings;

	return (
		<>
			<div className="wcpay-payments-activity-data__tpv">
				<div className="wcpay-payments-activity-data__tpv__label">
					{ __( 'Total payments volume', 'woocommerce-payments' ) }
					<HelpOutlineIcon />
				</div>
				<div className="wcpay-payments-activity-data__tpv__amount">
					{ formatCurrency( 3000, accountDefaultCurrency ) }
				</div>
			</div>
		</>
	);
};

export default TotalPaymentsVolume;
