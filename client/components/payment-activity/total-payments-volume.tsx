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
			<div className="total-payments-volume">
				<div className="total-payments-volume__heading">
					{ __( 'Total payments volume', 'woocommerce-payments' ) }
					<HelpOutlineIcon />
				</div>
				<div className="total-payments-volume__body">
					{ formatCurrency( 3000, accountDefaultCurrency ) }
				</div>
			</div>
		</>
	);
};

export default TotalPaymentsVolume;
