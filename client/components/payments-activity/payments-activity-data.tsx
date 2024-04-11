/**
 * External dependencies
 */
import * as React from 'react';
import moment from 'moment';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies.
 */
import PaymentsDataTile from './payments-data-tile';
import { ClickTooltip } from '../tooltip';
import { usePaymentActivityData } from 'wcpay/data';
import { getAdminUrl } from 'wcpay/utils';
import { DateRange, PaymentsActivityData } from './types';

import './style.scss';

/**
 * This will be replaces in the future with a dynamic date range picker.
 */
const getDateRange = (): DateRange => {
	return {
		// Subtract 7 days from the current date.
		date_start: moment()
			.subtract( 7, 'd' )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
		date_end: moment().format( 'YYYY-MM-DD\\THH:mm:ss' ),
	};
};

const PaymentsActivityData: React.FC = () => {
	const {
		paymentActivityData: {
			total_payments_volume: totalPaymentsVolume,
			charges,
			fees,
			disputes,
			refunds,
		},
		isLoading,
	} = usePaymentActivityData( getDateRange() ) as PaymentsActivityData;

	return (
		<div className="wcpay-payments-activity-data">
			<PaymentsDataTile
				id="wcpay-payments-activity-data__total-payments-volume"
				label={ __( 'Total payments volume', 'woocommerce-payments' ) }
				currencyCode="EUR"
				tooltip={
					<ClickTooltip
						className="total-payments-volume__tooltip"
						buttonIcon={ <HelpOutlineIcon /> }
						buttonLabel={ __(
							'Total payments volume tooltip',
							'woocommerce-payments'
						) }
						content={ __(
							'test total payments volume content',
							'woocommerce-payments'
						) }
					/>
				}
				amount={ totalPaymentsVolume }
				reportLink={ getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
				} ) }
				isLoading={ isLoading }
			/>
			<div className="wcpay-payments-data-highlights">
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__charges"
					label={ __( 'Charges', 'woocommerce-payments' ) }
					currencyCode="EUR"
					tooltip={
						<ClickTooltip
							className="payments-data-highlights__charges__tooltip"
							buttonIcon={ <HelpOutlineIcon /> }
							buttonLabel={ __(
								'Charges tooltip',
								'woocommerce-payments'
							) }
							content={ __( 'test charge content' ) }
						/>
					}
					amount={ charges }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'charge',
					} ) }
					isLoading={ isLoading }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__refunds"
					label={ __( 'Refunds', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ refunds }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'refund',
					} ) }
					isLoading={ isLoading }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__disputes"
					label={ __( 'Disputes', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ disputes }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes',
						filter: 'awaiting_response',
					} ) }
					isLoading={ isLoading }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__fees"
					label={ __( 'Fees', 'woocommerce-payments' ) }
					currencyCode="EUR"
					tooltip={
						<ClickTooltip
							className="payments-data-highlights__fees__tooltip"
							buttonIcon={ <HelpOutlineIcon /> }
							buttonLabel={ __(
								'Fees tooltip',
								'woocommerce-payments'
							) }
							content={ __(
								'test fees content',
								'woocommerce-payments'
							) }
						/>
					}
					amount={ fees }
					isLoading={ isLoading }
				/>
			</div>
		</div>
	);
};

export default PaymentsActivityData;
