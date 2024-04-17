/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies.
 */
import PaymentDataTile from './payment-data-tile';
import { ClickTooltip } from '../tooltip';
import { getAdminUrl } from 'wcpay/utils';
import './style.scss';

const PaymentActivityData: React.FC = () => {
	return (
		<div className="wcpay-payment-activity-data">
			<PaymentDataTile
				id="wcpay-payment-activity-data__total-payment-volume"
				label={ __( 'Total payment volume', 'woocommerce-payments' ) }
				currencyCode="EUR"
				amount={ 156373 }
				tooltip={
					<ClickTooltip
						className="total-payment-volume__tooltip"
						buttonIcon={ <HelpOutlineIcon /> }
						buttonLabel={ __(
							'Total payment volume tooltip',
							'woocommerce-payments'
						) }
						content={ __(
							'test total payment volume content',
							'woocommerce-payments'
						) }
					/>
				}
				reportLink={ getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
				} ) }
			/>
			<div className="wcpay-payment-data-highlights">
				<PaymentDataTile
					id="wcpay-payment-data-highlights__charges"
					label={ __( 'Charges', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 314300 }
					tooltip={
						<ClickTooltip
							className="payment-data-highlights__charges__tooltip"
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
				<PaymentDataTile
					id="wcpay-payment-data-highlights__refunds"
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
				<PaymentDataTile
					id="wcpay-payment-data-highlights__disputes"
					label={ __( 'Disputes', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 4727 }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes',
						filter: 'awaiting_response',
					} ) }
				/>
				<PaymentDataTile
					id="wcpay-payment-data-highlights__fees"
					label={ __( 'Fees', 'woocommerce-payments' ) }
					currencyCode="EUR"
					amount={ 9429 }
					tooltip={
						<ClickTooltip
							className="payment-data-highlights__fees__tooltip"
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

export default PaymentActivityData;
