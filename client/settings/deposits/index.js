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
} from '@wordpress/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';
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
import wcpayTracks from 'wcpay/tracks';

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

// Monthly deposit schedule anchors: 1-28 labelled with ordinal suffix and 31 labelled as "Last day of the month".
const monthlyAnchors = [
	...Array.from( { length: 28 }, ( _, i ) => i + 1 ),
	31,
].map( ( anchor ) => ( {
	value: anchor,
	label: getDepositMonthlyAnchorLabel( { monthlyAnchor: anchor } ),
} ) );

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
		}

		setDepositScheduleInterval( newInterval );
	};

	return (
		<>
			<div className="schedule-controls">
				<SelectControl
					label={ __( 'Frequency', 'woocommerce-payments' ) }
					value={ depositScheduleInterval }
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
					onClick={ () =>
						wcpayTracks.recordEvent(
							wcpayTracks.events
								.SETTINGS_DEPOSITS_SCHEDULE_DISABLED_DOCS_CLICK,
							{}
						)
					}
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
					onClick={ () =>
						wcpayTracks.recordEvent(
							wcpayTracks.events
								.SETTINGS_DEPOSITS_WAITING_PERIOD_DOCS_CLICK,
							{}
						)
					}
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
						{ __( 'Deposit bank account', 'woocommerce-payments' ) }
					</h4>
					<p className="deposits__bank-information-help">
						{ __(
							'Manage and update your deposit account information to receive payments and deposits.',
							'woocommerce-payments'
						) }{ ' ' }
						<ExternalLink
							href={ accountLink }
							onClick={ () =>
								wcpayTracks.recordEvent(
									wcpayTracks.events
										.SETTINGS_DEPOSITS_MANAGE_IN_STRIPE_CLICK,
									{}
								)
							}
						>
							{ __( 'Manage in Stripe', 'woocommerce-payments' ) }
						</ExternalLink>
					</p>
				</div>
			</CardBody>
		</Card>
	);
};

export default Deposits;
