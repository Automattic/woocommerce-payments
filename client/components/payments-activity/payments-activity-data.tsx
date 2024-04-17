/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies.
 */
import PaymentsDataTile from './payments-data-tile';
import { ClickTooltip } from '../tooltip';
import { getAdminUrl } from 'wcpay/utils';
import './style.scss';

const PaymentsActivityData: React.FC = () => {
	return (
		<div className="wcpay-payments-activity-data">
			<PaymentsDataTile
				id="wcpay-payments-activity-data__total-payments-volume"
				label={ __( 'Total payments volume', 'woocommerce-payments' ) }
				currencyCode="EUR"
				amount={ 156373 }
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
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'charge',
					} ) }
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
				/>
			</div>
		</div>
	);
};

export default PaymentsActivityData;
