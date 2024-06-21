/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import moment from 'moment';

/**
 * Internal dependencies
 */

import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import InlineLabelSelect from '../inline-label-select';
import PaymentActivityDataComponent from './payment-activity-data';
import Survey from './survey';
import { recordEvent } from 'wcpay/tracks';
import { usePaymentActivityData } from 'wcpay/data';
import { usePaymentActivityDateRangePresets } from './hooks';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
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

const formatDateRange = (
	start: moment.Moment,
	end: moment.Moment
): string => {
	// Today - show only today's date.
	if ( start.isSame( end, 'day' ) ) {
		return start.format( 'MMMM D, YYYY' );
	}

	// Different years - show year for both start and end
	if ( ! start.isSame( end, 'year' ) ) {
		return `${ start.format( 'MMMM D, YYYY' ) } - ${ end.format(
			'MMMM D, YYYY'
		) }`;
	}

	// Same year - show year only for end date.
	return `${ start.format( 'MMMM D' ) } - ${ end.format( 'MMMM D, YYYY' ) }`;
};

const PaymentActivity: React.FC = () => {
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	const { selectedCurrency } = useSelectedCurrency();
	const {
		selectedDateRange,
		setSelectedDateRange,
		dateRangePresets,
	} = usePaymentActivityDateRangePresets();
	const { paymentActivityData, isLoading } = usePaymentActivityData( {
		currency: selectedCurrency ?? wcpaySettings.accountDefaultCurrency,
		date_start: selectedDateRange.date_start,
		date_end: selectedDateRange.date_end,
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

	const options = Object.keys( dateRangePresets ).map( ( presetName ) => {
		const preset = dateRangePresets[ presetName ];
		return {
			key: presetName,
			name: preset.displayKey,
			hint: formatDateRange( preset.start, preset.end ),
		};
	} );

	return (
		<Card>
			<CardHeader className="wcpay-payment-activity__card__header">
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				<InlineLabelSelect
					label="Period"
					options={ options }
					value={ options.find(
						( option ) =>
							option.key === selectedDateRange.preset_name
					) }
					placeholder="Select an option..."
					onChange={ ( changes ) => {
						const selectedItem = changes.selectedItem;
						if ( selectedItem ) {
							const start = dateRangePresets[
								selectedItem.key
							].start
								.clone()
								.format( 'YYYY-MM-DD\\THH:mm:ss' );
							const end = dateRangePresets[ selectedItem.key ].end
								.clone()
								.format( 'YYYY-MM-DD\\THH:mm:ss' );
							const { key: presetName } = selectedItem;
							sessionStorage.setItem(
								'selectedPresetName',
								selectedItem.key
							);
							recordEvent(
								'wcpay_overview_payment_activity_period_change',
								{
									preset_name: presetName,
								}
							);
							setSelectedDateRange( {
								date_start: start,
								date_end: end,
								preset_name: presetName,
							} );
						}
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
