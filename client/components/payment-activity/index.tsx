/**
 * External dependencies
 */
import * as React from 'react';
import { useState } from 'react';
import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */

import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import PaymentActivityData from './payment-activity-data';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import './style.scss';

const PaymentActivity: React.FC = () => {
	const { lifetimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = lifetimeTPV > 0;
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	const [ presetDateRangeState, setPresetDateRangeState ] = useState(
		'last_7_days'
	);
	const presetDateRanges = [
		{
			value: 'today',
			label: 'Today',
		},
		{
			value: 'last_7_days',
			label: 'Last 7 days',
		},
		{
			value: 'last_4_weeks',
			label: 'Last 4 weeks',
		},
		{
			value: 'last_3_months',
			label: 'Last 3 months',
		},
		{
			value: 'last_12_months',
			label: 'Last 12 months',
		},
		{
			value: 'month_to_date',
			label: 'Month to date',
		},
		{
			value: 'quarter_to_date',
			label: 'Quarter to date',
		},
		{
			value: 'year_to_date',
			label: 'Year to date',
		},
		{
			value: 'all_time',
			label: 'All time',
		},
	];
	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }

				{ hasAtLeastOnePayment && (
					<>
						{
							<SelectControl
								value={ presetDateRangeState }
								onChange={ ( newPresetDateRange ) =>
									setPresetDateRangeState(
										newPresetDateRange
									)
								}
								options={ presetDateRanges }
							/>
						}
					</>
				) }
			</CardHeader>
			<CardBody className="wcpay-payment-activity__card__body">
				{ hasAtLeastOnePayment ? (
					<PaymentActivityData />
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
