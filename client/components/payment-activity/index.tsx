/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import moment from 'moment';

/**
 * Internal dependencies
 */

import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import PaymentActivityDataComponent from './payment-activity-data';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import { usePaymentActivityData } from 'wcpay/data';
import type { DateRange } from './types';
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

const PaymentActivity: React.FC = () => {
	const { lifetimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = lifetimeTPV > 0;
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	const { paymentActivityData, isLoading } = usePaymentActivityData(
		getDateRange()
	);

	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }

				{ hasAtLeastOnePayment && <>{ /* Filters go here */ }</> }
			</CardHeader>
			<CardBody className="wcpay-payment-activity__card__body">
				{ hasAtLeastOnePayment ? (
					<PaymentActivityDataComponent
						paymentActivityData={ paymentActivityData }
						isLoading={ isLoading }
					/>
				) : (
					<div className="wcpay-payment-activity__card__body__empty-state-wrapper">
						<img src={ EmptyStateAsset } alt="" />
						<p>
							{ interpolateComponents( {
								mixedString: __(
									'{{strong}}No payments…yet!{{/strong}}'
								),
								components: {
									strong: <strong />,
								},
							} ) }
						</p>
						<p>
							{ __(
								"Once your first order comes in, you'll start seeing your payment activity right here.",
								'woocommerce-payments'
							) }
						</p>
					</div>
				) }
			</CardBody>

			{ ! isOverviewSurveySubmitted && hasAtLeastOnePayment && (
				<WcPayOverviewSurveyContextProvider>
					<Survey />
				</WcPayOverviewSurveyContextProvider>
			) }
		</Card>
	);
};

export default PaymentActivity;
