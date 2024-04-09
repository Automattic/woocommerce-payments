/**
 * External dependencies
 */
import * as React from 'react';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { formatCurrency } from 'wcpay/utils/currency';
import { usePaymentActivityData } from 'wcpay/data';

import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import './style.scss';

const getDateRange = () => {
	return {
		date_start: moment()
			.subtract( 7, 'd' )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
		date_end: moment().format( 'YYYY-MM-DD\\THH:mm:ss' ),
	};
};

const TotalPaymentsVolume: React.FC = () => {
	const { accountDefaultCurrency } = wcpaySettings;

	const {
		paymentActivityData: { total_payments_volume: totalPaymentsVolume },
	} = usePaymentActivityData( getDateRange() );

	return (
		<>
			<div className="total-payments-volume">
				<div className="total-payments-volume__heading">
					{ __( 'Total payments volume', 'woocommerce-payments' ) }
					<HelpOutlineIcon />
				</div>
				<div className="total-payments-volume__body">
					{ formatCurrency(
						totalPaymentsVolume,
						accountDefaultCurrency
					) }
				</div>
			</div>
		</>
	);
};

export default TotalPaymentsVolume;
