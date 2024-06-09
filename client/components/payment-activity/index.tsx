/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import moment from 'moment';

/**
 * Internal dependencies
 */

import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import PaymentActivityDataComponent from './payment-activity-data';
import { defaultDateRange, DateRangePicker } from './date-range-picker';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import { usePaymentActivityData } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import './style.scss';

const PaymentActivityEmptyState: React.FC = () => (
	<Card>
		<CardHeader>
			{ __( 'Your payment activity', 'woocommerce-payments' ) }
		</CardHeader>
		<CardBody className="wcpay-payment-activity__card__body">
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
		</CardBody>
	</Card>
);

const PaymentActivity: React.FC = () => {
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	const { selectedCurrency } = useSelectedCurrency();

	const [ dateRange, setDateRange ] = useState( {
		date_start: defaultDateRange.date_start,
		date_end: defaultDateRange.date_end,
	} );

	const { paymentActivityData, isLoading } = usePaymentActivityData( {
		currency: selectedCurrency ?? wcpaySettings.accountDefaultCurrency,
		date_start: dateRange.date_start,
		date_end: dateRange.date_end,
		timezone: moment( new Date() ).format( 'Z' ),
	} );

	// When not loading and data is undefined, do not show widget.
	// This should only happen in 2 occasions:
	// 1. Initially on page load, and
	// 2. When we get an error from server.
	const showWidget = isLoading || paymentActivityData !== undefined;
	if ( ! showWidget ) {
		return <></>;
	}

	return (
		<Card>
			<CardHeader className="wcpay-payment-activity__card__header">
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				<DateRangePicker
					onDateRangeChange={ ( newDateRange ) => {
						setDateRange( newDateRange );
					} }
				/>
			</CardHeader>
			<CardBody className="wcpay-payment-activity__card__body">
				<PaymentActivityDataComponent
					paymentActivityData={ paymentActivityData }
					isLoading={ isLoading }
				/>
			</CardBody>

			{ ! isOverviewSurveySubmitted && (
				<WcPayOverviewSurveyContextProvider>
					<Survey />
				</WcPayOverviewSurveyContextProvider>
			) }
		</Card>
	);
};

const PaymentActivityWrapper: React.FC = () => {
	const { lifetimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = lifetimeTPV > 0;

	if ( ! hasAtLeastOnePayment ) {
		return <PaymentActivityEmptyState />;
	}

	return <PaymentActivity />;
};

export default PaymentActivityWrapper;
