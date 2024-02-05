/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Card, SelectControl, ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { STORE_NAME } from 'wcpay/data/constants';

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
	useDepositRestrictions,
} from '../../data';
import './style.scss';
import { recordEvent, events } from 'wcpay/tracks';
import InlineNotice from 'components/inline-notice';

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

	const settings = select( STORE_NAME ).getSettings();

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

	let depositIntervalsOptions = [
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
	];

	if ( settings.account_country === 'JP' ) {
		// Japanese accounts can't have daily payouts.
		depositIntervalsOptions = depositIntervalsOptions.slice( 1 );
	}

	return (
		<>
			<div className="schedule-controls">
				<SelectControl
					label={ __( 'Frequency', 'woocommerce-payments' ) }
					value={ depositScheduleInterval }
					onChange={ handleIntervalChange }
					options={ depositIntervalsOptions }
				/>
				{ depositScheduleInterval === 'monthly' && (
					<SelectControl
						label={ __( 'Date', 'woocommerce-payments' ) }
						value={ depositScheduleMonthlyAnchor }
						onChange={ setDepositScheduleMonthlyAnchor }
						options={ monthlyAnchors }
					/>
				) }
				{ depositScheduleInterval === 'weekly' && (
					<SelectControl
						label={ __( 'Day', 'woocommerce-payments' ) }
						value={ depositScheduleWeeklyAnchor }
						onChange={ setDepositScheduleWeeklyAnchor }
						options={ daysOfWeek }
					/>
				) }
			</div>
			<p className="help-text">
				{ depositScheduleInterval === 'monthly' &&
					__(
						'Deposits scheduled on a weekend will be sent on the next business day.',
						'woocommerce-payments'
					) }
				{ depositScheduleInterval === 'weekly' &&
					__(
						'Deposits that fall on a holiday will initiate on the next business day.',
						'woocommerce-payments'
					) }
				{ depositScheduleInterval === 'daily' &&
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
	const depositRestrictions = useDepositRestrictions();
	const completedWaitingPeriod = useCompletedWaitingPeriod();

	if (
		depositStatus !== 'enabled' ||
		depositRestrictions === 'schedule_restricted'
	) {
		return (
			<InlineNotice status="warning" isDismissible={ false } icon>
				{ interpolateComponents( {
					mixedString: __(
						'Deposit scheduling is currently unavailable for your store. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-payments'
					),
					components: {
						learnMoreLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://woo.com/document/woopayments/deposits/deposit-schedule/"
								target="_blank"
								rel="noreferrer noopener"
							/>
						),
					},
				} ) }
			</InlineNotice>
		);
	}
	if ( completedWaitingPeriod !== true ) {
		return (
			<InlineNotice status="warning" isDismissible={ false } icon>
				{ interpolateComponents( {
					mixedString: __(
						'Your first deposit will be held for 7-14 days. ' +
							'Deposit scheduling will be available after this period. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-payments'
					),
					components: {
						learnMoreLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://woo.com/document/woopayments/deposits/deposit-schedule/"
								target="_blank"
								rel="noreferrer noopener"
							/>
						),
					},
				} ) }
			</InlineNotice>
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
								recordEvent(
									events.SETTINGS_DEPOSITS_MANAGE_IN_STRIPE_CLICK,
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
