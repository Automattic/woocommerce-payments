/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	SelectControl,
	ExternalLink,
	Notice,
	RadioControl,
} from '@wordpress/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import WCPaySettingsContext from '../wcpay-settings-context';
import CardBody from '../card-body';
import {
	useDepositScheduleInterval,
	useDepositScheduleWeeklyAnchor,
	useDepositScheduleMonthlyAnchor,
	useDepositStatus,
	useCompletedWaitingPeriod,
} from '../../data';
import './style.scss';

const daysOfWeek = [
	{ label: __( 'Monday', 'woocommerce-payments' ), value: 'monday' },
	{ label: __( 'Tuesday', 'woocommerce-payments' ), value: 'tuesday' },
	{
		label: __( 'Wednesday', 'woocommerce-payments' ),
		value: 'wednesday',
	},
	{ label: __( 'Thursday', 'woocommerce-payments' ), value: 'thursday' },
	{ label: __( 'Friday', 'woocommerce-payments' ), value: 'friday' },
];

const monthlyAnchors = [
	{ label: __( '1st', 'woocommerce-payments' ), value: 1 },
	{ label: __( '2nd', 'woocommerce-payments' ), value: 2 },
	{ label: __( '3rd', 'woocommerce-payments' ), value: 3 },
	{ label: __( '4th', 'woocommerce-payments' ), value: 4 },
	{ label: __( '5th', 'woocommerce-payments' ), value: 5 },
	{ label: __( '6th', 'woocommerce-payments' ), value: 6 },
	{ label: __( '7th', 'woocommerce-payments' ), value: 7 },
	{ label: __( '8th', 'woocommerce-payments' ), value: 8 },
	{ label: __( '9th', 'woocommerce-payments' ), value: 9 },
	{ label: __( '10th', 'woocommerce-payments' ), value: 10 },
	{ label: __( '11th', 'woocommerce-payments' ), value: 11 },
	{ label: __( '12th', 'woocommerce-payments' ), value: 12 },
	{ label: __( '13th', 'woocommerce-payments' ), value: 13 },
	{ label: __( '14th', 'woocommerce-payments' ), value: 14 },
	{ label: __( '15th', 'woocommerce-payments' ), value: 15 },
	{ label: __( '16th', 'woocommerce-payments' ), value: 16 },
	{ label: __( '17th', 'woocommerce-payments' ), value: 17 },
	{ label: __( '18th', 'woocommerce-payments' ), value: 18 },
	{ label: __( '19th', 'woocommerce-payments' ), value: 19 },
	{ label: __( '20th', 'woocommerce-payments' ), value: 20 },
	{ label: __( '21st', 'woocommerce-payments' ), value: 21 },
	{ label: __( '22nd', 'woocommerce-payments' ), value: 22 },
	{ label: __( '23rd', 'woocommerce-payments' ), value: 23 },
	{ label: __( '24th', 'woocommerce-payments' ), value: 24 },
	{ label: __( '25th', 'woocommerce-payments' ), value: 25 },
	{ label: __( '26th', 'woocommerce-payments' ), value: 26 },
	{ label: __( '27th', 'woocommerce-payments' ), value: 27 },
	{ label: __( '28th', 'woocommerce-payments' ), value: 28 },
	{
		label: __( 'Last day of the month', 'woocommerce-payments' ),
		value: 31,
	},
];

const CustomizeDepositSchedule = () => {
	const [
		depositScheduleInterval,
		setDepositScheduleInterval,
	] = useDepositScheduleInterval();
	const [
		depositScheduleWeeklyAnchor,
		setDepositScheduleWeeklyAnchor,
	] = useDepositScheduleWeeklyAnchor();
	const [
		depositScheduleMonthlyAnchor,
		setDepositScheduleMonthlyAnchor,
	] = useDepositScheduleMonthlyAnchor();

	const handleIntervalChange = ( newInterval ) => {
		switch ( newInterval ) {
			case 'weekly':
				setDepositScheduleWeeklyAnchor(
					depositScheduleWeeklyAnchor || 'monday'
				);
				break;

			case 'monthly':
				setDepositScheduleMonthlyAnchor(
					depositScheduleMonthlyAnchor || '1'
				);
				break;
			case 'manual':
				break;
			default:
				newInterval = 'daily';
		}

		setDepositScheduleInterval( newInterval );
	};

	return (
		<>
			<RadioControl
				selected={
					'manual' !== depositScheduleInterval
						? ''
						: depositScheduleInterval
				}
				onChange={ handleIntervalChange }
				options={ [
					{
						label: __(
							'Automatic Deposits',
							'woocommerce-payments'
						),
						value: '',
					},
				] }
			/>
			{ 'manual' !== depositScheduleInterval && (
				<div className="schedule-sub-radio">
					<div className="schedule-controls">
						<SelectControl
							label={ __( 'Frequency', 'woocommerce-payments' ) }
							value={ depositScheduleInterval }
							onChange={ handleIntervalChange }
							options={ [
								{
									value: 'daily',
									label: __(
										'Daily',
										'woocommerce-payments'
									),
								},
								{
									value: 'weekly',
									label: __(
										'Weekly',
										'woocommerce-payments'
									),
								},
								{
									value: 'monthly',
									label: __(
										'Monthly',
										'woocommerce-payments'
									),
								},
							] }
						/>
						{ 'monthly' === depositScheduleInterval && (
							<SelectControl
								label={ __( 'Date', 'woocommerce-payments' ) }
								value={ depositScheduleMonthlyAnchor }
								onChange={ setDepositScheduleMonthlyAnchor }
								options={ monthlyAnchors }
							/>
						) }
						{ 'weekly' === depositScheduleInterval && (
							<SelectControl
								label={ __( 'Day', 'woocommerce-payments' ) }
								value={ depositScheduleWeeklyAnchor }
								onChange={ setDepositScheduleWeeklyAnchor }
								options={ daysOfWeek }
							/>
						) }
					</div>
					<p className="help-text">
						{ 'monthly' === depositScheduleInterval &&
							__(
								'Deposits scheduled on a weekend will be sent on the next business day.',
								'woocommerce-payments'
							) }
						{ 'weekly' === depositScheduleInterval &&
							__(
								'Deposits that fall on a holiday will initiate on the next business day.',
								'woocommerce-payments'
							) }
						{ 'daily' === depositScheduleInterval &&
							__(
								'Deposits will occur every business day.',
								'woocommerce-payments'
							) }
					</p>
				</div>
			) }
			<RadioControl
				className="schedule-manual-radio"
				selected={ depositScheduleInterval }
				onChange={ handleIntervalChange }
				options={ [
					{
						label: __( 'Manual Deposits', 'woocommerce-payments' ),
						value: 'manual',
					},
				] }
				help={ __(
					'Initiate a deposit at any time from the Payments dashboard.',
					'woocommerce-payments'
				) }
			/>
		</>
	);
};
const DepositsSchedule = () => {
	const depositStatus = useDepositStatus();
	const completedWaitingPeriod = useCompletedWaitingPeriod();

	if ( 'enabled' !== depositStatus ) {
		return (
			<Notice
				status="warning"
				isDismissible={ false }
				className="deposits__notice"
			>
				<span>
					{ __(
						'Deposit scheduling is currently unavailable for your store.',
						'woocommerce-payments'
					) }
				</span>
				<a
					aria-label={ __(
						'Learn more about deposit scheduling.',
						'woocommerce-payments'
					) }
					href="https://woocommerce.com/document/payments/faq/deposit-schedule/"
					target="_blank"
					rel="external noreferrer noopener"
				>
					<Gridicon icon="help-outline" size={ 18 } />
				</a>
			</Notice>
		);
	}
	if ( true !== completedWaitingPeriod ) {
		return (
			<Notice
				status="warning"
				isDismissible={ false }
				className="deposits__notice"
			>
				<span>
					{ __(
						'Your first deposit will be held for 7 days. Deposit scheduling will be available after this period.',
						'woocommerce-payments'
					) }
				</span>
				<a
					aria-label={ __(
						'Learn more about deposit scheduling.',
						'woocommerce-payments'
					) }
					href="https://woocommerce.com/document/payments/faq/deposit-schedule/"
					target="_blank"
					rel="external noreferrer noopener"
				>
					<Gridicon icon="help-outline" size={ 18 } />
				</a>
			</Notice>
		);
	}

	return <CustomizeDepositSchedule />;
};

const Deposits = () => {
	const {
		accountStatus: { accountLink },
	} = useContext( WCPaySettingsContext );

	return (
		<Card className="deposits">
			<CardBody>
				<h4>{ __( 'Deposit schedule', 'woocommerce-payments' ) }</h4>

				<DepositsSchedule />

				<div className="deposits__bank-information">
					<h4>
						{ __(
							'Bank account information',
							'woocommerce-payments'
						) }
					</h4>
					<p className="deposits__bank-information-help">
						{ __(
							'Manage and update your deposit account information to receive payments and payouts.',
							'woocommerce-payments'
						) }{ ' ' }
						<ExternalLink href={ accountLink }>
							{ __( 'Manage in Stripe', 'woocommerce-payments' ) }
						</ExternalLink>
					</p>
				</div>
			</CardBody>
		</Card>
	);
};

export default Deposits;
