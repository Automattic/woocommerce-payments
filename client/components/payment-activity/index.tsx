/**
 * External dependencies
 */
import * as React from 'react';
import moment from 'moment';
import { useState } from 'react';
import {
	Card,
	CardBody,
	CardHeader,
	Flex,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import PaymentActivityData from './payment-activity-data';
import DateRange from './date-range';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import './style.scss';

interface DateRange {
	start: moment.Moment | undefined;
	end: moment.Moment | undefined;
}

const PaymentActivity: React.FC = () => {
	const { lifetimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = lifetimeTPV > 0;
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	const [ dateRangeState, setDateRangeState ] = useState( {
		start: moment().clone().subtract( 7, 'd' ),
		end: moment().clone().subtract( 1, 'd' ),
	} as DateRange );
	const [ dateRangePresetState, setDateRangePresetState ] = useState(
		'last_7_days'
	);
	const dateRangePresets = [
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
	const dateRangePresetOnChangeHandler = ( newDateRangePreset: string ) => {
		let start, end;

		setDateRangePresetState( newDateRangePreset );

		switch ( newDateRangePreset ) {
			case 'today': {
				const now = moment();
				start = now
					.clone()
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'last_7_days': {
				const now = moment();
				start = now
					.clone()
					.subtract( 7, 'd' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'last_4_weeks': {
				const now = moment();
				start = now
					.clone()
					.subtract( 4, 'w' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'last_3_months': {
				const now = moment();
				start = now
					.clone()
					.subtract( 3, 'm' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'last_12_months': {
				const now = moment();
				start = now
					.clone()
					.subtract( 12, 'm' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'month_to_date': {
				const now = moment();
				start = now.clone().set( {
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'quarter_to_date': {
				const now = moment();
				start = now.clone().set( {
					month: Math.floor( now.month() / 3 ) * 3,
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'year_to_date': {
				const now = moment();
				start = now.clone().set( {
					month: 0,
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = now.clone().set( {
					hour: 23,
					minute: 59,
					second: 59,
					millisecond: 0,
				} );
				break;
			}
			case 'all_time':
				// TODO
				break;
		}

		setDateRangeState( { start, end } );
	};

	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }

				{ hasAtLeastOnePayment && (
					<>
						<Flex className="wcpay-payment-activity-filters">
							<SelectControl
								value={ dateRangePresetState }
								onChange={ dateRangePresetOnChangeHandler }
								options={ dateRangePresets }
							/>
							<DateRange
								start={
									dateRangeState.start
										? dateRangeState.start.format(
												'YYYY-MM-DD'
										  )
										: ''
								}
								end={
									dateRangeState.end
										? dateRangeState.end.format(
												'YYYY-MM-DD'
										  )
										: ''
								}
							/>
						</Flex>
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
