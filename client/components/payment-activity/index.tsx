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
import { DateRangePicker } from './date-range-picker';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import { usePaymentActivityData } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import type { DateRange } from './types';
import './style.scss';

/**
 * This will be replaces in the future with a dynamic date range picker.
 * Commenting this off for now. We can totally remove this later.
 */
/* const getDateRange = (): DateRange => {
	return {
		// Subtract 7 days from the current date.
		date_start: moment()
			.subtract( 7, 'd' )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
		date_end: moment().format( 'YYYY-MM-DD\\THH:mm:ss' ),
	};
}; */

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
		date_start: moment()
			.subtract( 7, 'd' )
			.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
		date_end: moment()
			.subtract( 1, 'd' )
			.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } )
			.format( 'YYYY-MM-DD\\THH:mm:ss' ),
	} );

	const { paymentActivityData, isLoading } = usePaymentActivityData( {
		currency: selectedCurrency ?? wcpaySettings.accountDefaultCurrency,
		...dateRange,
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

	const handleDataFromDateRangePicker = ( newDateRange: DateRange ) => {
		// eslint-disable-next-line no-console -- We need to log the date range for debugging purposes. This needs to be removed before merging.
		console.log( newDateRange );
		setDateRange( newDateRange );
	};

	return (
		<Card>
			<CardHeader className="wcpay-payment-activity__card__header">
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				<DateRangePicker
					sendDateRangeToParent={ handleDataFromDateRangePicker }
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
