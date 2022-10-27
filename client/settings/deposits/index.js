/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	SelectControl,
	ExternalLink,
	Notice,
	RadioControl,
} from '@wordpress/components';
import { createInterpolateElement, useState } from '@wordpress/element';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

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
import { formatCurrency } from 'utils/currency';
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
	const {
		accountStatus: {
			deposits: { minimum_deposit_amounts: minimumDepositAmounts },
		},
		storeCurrencies,
	} = useContext( WCPaySettingsContext );

	const defaultStoreCurrency = storeCurrencies.default || 'usd';
	const minimumDepositAmount =
		minimumDepositAmounts?.[ defaultStoreCurrency ] || 500;

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

	const [ automaticDepositSchedule, setAutomaticDepositSchedule ] = useState(
		'manual' === depositScheduleInterval ? 'daily' : depositScheduleInterval
	);

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
			case 'automatic':
			case 'manual':
				break;
			default:
				newInterval = 'daily';
		}

		if ( 'automatic' === newInterval ) {
			newInterval = automaticDepositSchedule;
		}

		setDepositScheduleInterval( newInterval );
		if (
			'manual' !== newInterval &&
			newInterval !== automaticDepositSchedule
		) {
			setAutomaticDepositSchedule( newInterval );
		}
	};

	const automaticControlsDisabled = 'manual' === depositScheduleInterval;

	const depositSettingsDocsUrl =
		'https://woocommerce.com/document/payments/#section-15';

	return (
		<>
			<RadioControl
				selected={
					'manual' !== depositScheduleInterval
						? 'automatic'
						: depositScheduleInterval
				}
				onChange={ handleIntervalChange }
				options={ [
					{
						label: __( 'Automatic', 'woocommerce-payments' ),
						value: 'automatic',
					},
				] }
			/>
			<div className="schedule-sub-radio">
				<div className="schedule-controls">
					<SelectControl
						disabled={ automaticControlsDisabled }
						label={ __( 'Frequency', 'woocommerce-payments' ) }
						value={ automaticDepositSchedule }
						onChange={ handleIntervalChange }
						options={ [
							{
								value: 'daily',
								label: __( 'Daily', 'woocommerce-payments' ),
							},
							{
								value: 'weekly',
								label: __( 'Weekly', 'woocommerce-payments' ),
							},
							{
								value: 'monthly',
								label: __( 'Monthly', 'woocommerce-payments' ),
							},
						] }
					/>
					{ 'monthly' === automaticDepositSchedule && (
						<SelectControl
							disabled={ automaticControlsDisabled }
							label={ __( 'Date', 'woocommerce-payments' ) }
							value={ depositScheduleMonthlyAnchor }
							onChange={ setDepositScheduleMonthlyAnchor }
							options={ monthlyAnchors }
						/>
					) }
					{ 'weekly' === automaticDepositSchedule && (
						<SelectControl
							disabled={ automaticControlsDisabled }
							label={ __( 'Day', 'woocommerce-payments' ) }
							value={ depositScheduleWeeklyAnchor }
							onChange={ setDepositScheduleWeeklyAnchor }
							options={ daysOfWeek }
						/>
					) }
				</div>
				<p className="help-text">
					{ createInterpolateElement(
						__(
							'Deposits that fall on a holiday or a weekend will initiate on the next business day. <a>Learn more</a>',
							'woocommerce-payments'
						),
						{
							a: <ExternalLink href={ depositSettingsDocsUrl } />,
						}
					) }
				</p>
			</div>
			<RadioControl
				className="schedule-manual-radio"
				selected={ depositScheduleInterval }
				onChange={ handleIntervalChange }
				options={ [
					{
						label: __( 'Manual', 'woocommerce-payments' ),
						value: 'manual',
					},
				] }
				help={ createInterpolateElement(
					sprintf(
						__(
							// eslint-disable-next-line max-len
							'Create a deposit from the Payments Overview. Deposits are available once per day, with a minimum available balance of %s. <a>Learn more</a>',
							'woocommerce-payments'
						),
						formatCurrency(
							minimumDepositAmount,
							defaultStoreCurrency
						)
					),
					{
						a: <ExternalLink href={ depositSettingsDocsUrl } />,
					}
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
					<HelpOutlineIcon size={ 18 } />
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
					<HelpOutlineIcon size={ 18 } />
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
