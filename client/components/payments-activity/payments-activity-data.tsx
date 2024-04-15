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
	const { paymentActivityData, isLoading } = usePaymentActivityData(
		getDateRange()
	);

	const totalPaymentsVolume = paymentActivityData?.total_payment_volume ?? 0;
	const charges = paymentActivityData?.charges ?? 0;
	const fees = paymentActivityData?.fees ?? 0;
	const disputes = paymentActivityData?.disputes ?? 0;
	const refunds = paymentActivityData?.refunds ?? 0;
	const { storeCurrency } = wcpaySettings;

	return (
		<div className="wcpay-payments-activity-data">
			<PaymentsDataTile
				id="wcpay-payments-activity-data__total-payments-volume"
				label={ __( 'Total payments volume', 'woocommerce-payments' ) }
				currencyCode={ storeCurrency }
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
					'date_between[0]': moment(
						getDateRange().date_start
					).format( 'YYYY-MM-DD' ),
					'date_between[1]': moment( getDateRange().date_end ).format(
						'YYYY-MM-DD'
					),
					filter: 'advanced',
				} ) }
				isLoading={ isLoading }
			/>
			<div className="wcpay-payments-data-highlights">
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__charges"
					label={ __( 'Charges', 'woocommerce-payments' ) }
					currencyCode={ storeCurrency }
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
					currencyCode={ storeCurrency }
					amount={ refunds }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'refund',
						'date_between[0]': moment(
							getDateRange().date_start
						).format( 'YYYY-MM-DD' ),
						'date_between[1]': moment(
							getDateRange().date_end
						).format( 'YYYY-MM-DD' ),
					} ) }
					isLoading={ isLoading }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__disputes"
					label={ __( 'Disputes', 'woocommerce-payments' ) }
					currencyCode={ storeCurrency }
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
					currencyCode={ storeCurrency }
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
