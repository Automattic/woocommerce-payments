/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */
import PaymentDataTile from './payment-data-tile';
import { ClickTooltip } from '../tooltip';
import InlineNotice from '../inline-notice';
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
						className="wcpay-payment-activity-data__total-payment-volume__tooltip"
						buttonIcon={ <HelpOutlineIcon /> }
						buttonLabel={ __(
							'Total payment volume tooltip',
							'woocommerce-payments'
						) }
						content={
							<>
								{ interpolateComponents( {
									mixedString: __(
										'{{strong}}Total payment volume{{/strong}} is gross value of payments successfully processed over a given timeframe.',
										'woocommerce-payments'
									),
									components: {
										strong: <strong />,
									},
								} ) }
								<InlineNotice
									className="wcpay-payment-activity-data__total-payment-volume__tooltip__notice"
									isDismissible={ false }
								>
									{ __(
										'Total payment volume = Charges - Refunds - Disputes',
										'woocommerce-payments'
									) }
								</InlineNotice>
							</>
						}
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
							content={ interpolateComponents( {
								mixedString: __(
									'A {{strong}}charge{{/strong}} is the amount billed to your customer’s payment method.',
									'woocommerce-payments'
								),
								components: {
									strong: <strong />,
								},
							} ) }
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
							content={ interpolateComponents( {
								mixedString: __(
									'{{strong}}Fees{{/strong}} includes fees on payments as well as disputes.',
									'woocommerce-payments'
								),
								components: {
									strong: <strong />,
								},
							} ) }
						/>
					}
				/>
			</div>
		</div>
	);
};

export default PaymentActivityData;
