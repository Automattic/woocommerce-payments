/**
 * External dependencies
 */
import * as React from 'react';
import moment from 'moment';
import { __ } from '@wordpress/i18n';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */
import InlineNotice from '../inline-notice';
import PaymentDataTile from './payment-data-tile';
import { ClickTooltip } from '../tooltip';
import { usePaymentActivityData } from 'wcpay/data';
import { getAdminUrl } from 'wcpay/utils';
import type { DateRange } from './types';
import './style.scss';

/**
 * This will be replaces in the future with a dynamic date range picker.
 */
const getDateRange = (): DateRange => {
	return {
		// Subtract 6 days from the current date. 7 days including the current day.
		date_start: moment()
			.subtract( 6, 'd' )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
		date_end: moment().format( 'YYYY-MM-DD\\THH:mm:ss' ),
	};
};

const searchTermsForViewReportLink = {
	totalPaymentVolume: [
		'charge',
		'payment',
		'payment_failure_refund',
		'payment_refund',
		'refund',
		'refund_failure',
		'dispute',
		'dispute_reversal',
		'card_reader_fee',
	],
};

const getSearchParams = ( searchTerms: string[] ) => {
	return searchTerms.reduce(
		( acc, term, index ) => ( {
			...acc,
			[ `search[${ index }]` ]: term,
		} ),
		{}
	);
};

const PaymentActivityData: React.FC = () => {
	const { paymentActivityData, isLoading } = usePaymentActivityData(
		getDateRange()
	);

	const totalPaymentVolume = paymentActivityData?.total_payment_volume ?? 0;
	const charges = paymentActivityData?.charges ?? 0;
	const fees = paymentActivityData?.fees ?? 0;
	const disputes = paymentActivityData?.disputes ?? 0;
	const refunds = paymentActivityData?.refunds ?? 0;
	const currency = paymentActivityData?.currency;

	return (
		<div className="wcpay-payment-activity-data">
			<PaymentDataTile
				id="wcpay-payment-activity-data__total-payment-volume"
				label={ __( 'Total payment volume', 'woocommerce-payments' ) }
				currencyCode={ currency }
				tooltip={
					<ClickTooltip
						className="wcpay-payment-activity-data__total-payment-volume__tooltip"
						maxWidth={ '294px' }
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
				amount={ totalPaymentVolume }
				reportLink={ getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
					filter: 'advanced',
					'date_between[0]': moment(
						getDateRange().date_start
					).format( 'YYYY-MM-DD' ),
					'date_between[1]': moment( getDateRange().date_end ).format(
						'YYYY-MM-DD'
					),
					...getSearchParams(
						searchTermsForViewReportLink.totalPaymentVolume
					),
				} ) }
				tracksSource="total_payment_volume"
				isLoading={ isLoading }
			/>
			<div className="wcpay-payment-data-highlights">
				<PaymentDataTile
					id="wcpay-payment-data-highlights__charges"
					label={ __( 'Charges', 'woocommerce-payments' ) }
					currencyCode={ currency }
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
					amount={ charges }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/transactions',
						filter: 'advanced',
						type_is: 'charge',
					} ) }
					tracksSource="charges"
					isLoading={ isLoading }
				/>
				<PaymentDataTile
					id="wcpay-payment-data-highlights__refunds"
					label={ __( 'Refunds', 'woocommerce-payments' ) }
					currencyCode={ currency }
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
					tracksSource="refunds"
					isLoading={ isLoading }
				/>
				<PaymentDataTile
					id="wcpay-payment-data-highlights__disputes"
					label={ __( 'Disputes', 'woocommerce-payments' ) }
					currencyCode={ currency }
					amount={ disputes }
					reportLink={ getAdminUrl( {
						page: 'wc-admin',
						path: '/payments/disputes',
						filter: 'advanced',
						'date_between[0]': moment(
							getDateRange().date_start
						).format( 'YYYY-MM-DD' ),
						'date_between[1]': moment(
							getDateRange().date_end
						).format( 'YYYY-MM-DD' ),
						status_is: 'needs_response',
					} ) }
					tracksSource="disputes"
					isLoading={ isLoading }
				/>
				<PaymentDataTile
					id="wcpay-payment-data-highlights__fees"
					label={ __( 'Fees', 'woocommerce-payments' ) }
					currencyCode={ currency }
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
					amount={ fees }
					isLoading={ isLoading }
				/>
			</div>
		</div>
	);
};

export default PaymentActivityData;
