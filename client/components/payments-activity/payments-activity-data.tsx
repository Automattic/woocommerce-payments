/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import PaymentsDataTile from './payments-data-tile';
import {
	TotalPaymentsVolumeTooltip,
	PaymentsDataChargeTooltip,
	PaymentsDataFeesTooltip,
} from './payments-data-highlights-tooltips';

import './style.scss';
import { getAdminUrl } from 'wcpay/utils';
import { getIsManualCaptureEnabled } from 'wcpay/data/settings/selectors';

const PaymentsActivityData: React.FC = () => {
	const isManualCaptureEnabled = getIsManualCaptureEnabled();
	return (
		<div className="wcpay-payments-activity-data">
			<PaymentsDataTile
				id="wcpay-payments-activity-data__total-payments-volume"
				label={ __( 'Total payments volume', 'woocommerce-payments' ) }
				currencyCode="USD"
				amount={ 156373 }
				tooltip={ <TotalPaymentsVolumeTooltip /> }
				reportLink={ getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
				} ) }
			/>
			<div className="wcpay-payments-data-highlights">
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__charges"
					label={ __( 'Charges', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 314300 }
					tooltip={ <PaymentsDataChargeTooltip /> }
					reportLink={
						isManualCaptureEnabled &&
						getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/transactions',
							tab: 'uncaptured-page',
						} )
					}
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__refunds"
					label={ __( 'Refunds', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 153200 }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'refund',
					} ) }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__disputes"
					label={ __( 'Disputes', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 4727 }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes',
						filter: 'awaiting_response',
					} ) }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__fees"
					label={ __( 'Fees', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 9429 }
					tooltip={ <PaymentsDataFeesTooltip /> }
				/>
			</div>
		</div>
	);
};

export default PaymentsActivityData;
