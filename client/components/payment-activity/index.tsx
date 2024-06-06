/**
 * External dependencies
 */
import * as React from 'react';
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
import moment from 'moment';

/**
 * Internal dependencies
 */

import DateRange from './date-range';
import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import PaymentActivityDataComponent from './payment-activity-data';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import { usePaymentActivityData } from 'wcpay/data';
import './style.scss';

interface DateRangeProps {
	start: moment.Moment | undefined;
	end: moment.Moment | undefined;
}

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

	const yesterdayEndOfDay = moment()
		.clone()
		.subtract( 1, 'd' )
		.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );

	const todayEndOfDay = moment()
		.clone()
		.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );

	const [ dateRangeState, setDateRangeState ] = useState( {
		start: moment().clone().subtract( 7, 'd' ),
		end: yesterdayEndOfDay,
	} as DateRangeProps );

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
		const now = moment();
		setDateRangePresetState( newDateRangePreset );

		switch ( newDateRangePreset ) {
			case 'today': {
				start = now
					.clone()
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = todayEndOfDay;
				break;
			}
			case 'last_7_days': {
				start = now
					.clone()
					.subtract( 7, 'days' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = yesterdayEndOfDay;
				break;
			}
			case 'last_4_weeks': {
				start = now
					.clone()
					.subtract( 4, 'weeks' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = yesterdayEndOfDay;
				break;
			}
			case 'last_3_months': {
				start = now
					.clone()
					.subtract( 3, 'months' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = yesterdayEndOfDay;
				break;
			}
			case 'last_12_months': {
				start = now
					.clone()
					.subtract( 12, 'months' )
					.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } );
				end = yesterdayEndOfDay;
				break;
			}
			case 'month_to_date': {
				start = now.clone().set( {
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = todayEndOfDay;
				break;
			}
			case 'quarter_to_date': {
				start = now.clone().set( {
					month: Math.floor( now.month() / 3 ) * 3,
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = todayEndOfDay;
				break;
			}
			case 'year_to_date': {
				start = now.clone().set( {
					month: 0,
					date: 1,
					hour: 0,
					minute: 0,
					second: 0,
					millisecond: 0,
				} );
				end = todayEndOfDay;
				break;
			}
			case 'all_time':
				start = moment(
					wcpaySettings.accountStatus.created,
					'YYYY-MM-DD HH:mm:ss'
				);
				end = todayEndOfDay;
				break;
		}

		setDateRangeState( { start, end } );
	};

	const { paymentActivityData, isLoading } = usePaymentActivityData( {
		// In future this will be bound to currency picker via useSelectedCurrency().
		// Can hard-code other store settings to test.
		currency: wcpaySettings.accountDefaultCurrency,
		date_start: dateRangeState.start
			? dateRangeState.start.format( 'YYYY-MM-DDTHH:mm:ss' )
			: '',
		date_end: dateRangeState.end
			? dateRangeState.end.format( 'YYYY-MM-DDTHH:mm:ss' )
			: '',
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
				<h1>
					{ __( 'Your payment activity', 'woocommerce-payments' ) }
				</h1>
				<Flex className="wcpay-payment-activity-filters">
					<SelectControl
						value={ dateRangePresetState }
						onChange={ dateRangePresetOnChangeHandler }
						options={ dateRangePresets }
					/>
					<DateRange
						start={ dateRangeState.start }
						end={ dateRangeState.end }
					/>
				</Flex>
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
	if ( wcpaySettings.lifetimeTPV <= 0 ) {
		return <PaymentActivityEmptyState />;
	}

	return <PaymentActivity />;
};

export default PaymentActivityWrapper;
