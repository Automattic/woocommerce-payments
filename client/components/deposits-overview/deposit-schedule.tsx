/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import moment from 'moment';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';
import {
	hasAutomaticScheduledDeposits,
	getDepositMonthlyAnchorLabel,
} from 'wcpay/deposits/utils';
import type * as AccountOverview from 'wcpay/types/account-overview';

interface DepositScheduleProps {
	depositsSchedule: AccountOverview.Account[ 'deposits_schedule' ];
}

/**
 * Renders a rich-text sentence summarising the deposit schedule.
 *
 * eg "Your deposits are dispatched automatically every day"
 */
const DepositScheduleSummary: React.FC< DepositScheduleProps > = ( {
	depositsSchedule,
} ) => {
	switch ( depositsSchedule.interval ) {
		case 'daily':
			return interpolateComponents( {
				mixedString: sprintf(
					/** translators: {{strong}}: placeholders are opening and closing strong tags. */
					__(
						'Available funds are automatically dispatched {{strong}}every day{{/strong}}.',
						'woocommerce-payments'
					)
				),
				components: {
					strong: <strong />,
				},
			} );
		case 'weekly':
			const dayOfWeek = moment()
				.locale( 'en' )
				.day( depositsSchedule.weekly_anchor )
				.locale( moment.locale() )
				.format( 'dddd' );

			return interpolateComponents( {
				mixedString: sprintf(
					/** translators: %1$s: is the day of the week. eg "Friday". {{strong}}: placeholders are opening and closing strong tags. */
					__(
						'Available funds are automatically dispatched {{strong}}every %1$s{{/strong}}.',
						'woocommerce-payments'
					),
					dayOfWeek
				),
				components: {
					strong: <strong />,
				},
			} );
		case 'monthly':
			const monthlyAnchor = depositsSchedule.monthly_anchor;

			// If the monthly anchor is 31, it means the deposit is scheduled for the last day of the month and has special handling.
			if ( monthlyAnchor === 31 ) {
				return interpolateComponents( {
					mixedString: sprintf(
						/** translators: {{strong}}: placeholders are opening and closing strong tags. */
						__(
							'Available funds are automatically dispatched {{strong}}on the last day of every month{{/strong}}.',
							'woocommerce-payments'
						)
					),
					components: {
						strong: <strong />,
					},
				} );
			}

			return interpolateComponents( {
				mixedString: sprintf(
					/** translators: {{strong}}: placeholders are opening and closing strong tags. %1$s: is the day of the month. eg "31st". */
					__(
						'Available funds are automatically dispatched {{strong}}on the %1$s of every month{{/strong}}.',
						'woocommerce-payments'
					),
					getDepositMonthlyAnchorLabel( {
						monthlyAnchor: monthlyAnchor,
						capitalize: false,
					} )
				),
				components: {
					strong: <strong />,
				},
			} );
		default:
			return <></>;
	}
};

/**
 * Renders a summary of the deposit schedule & a tooltip so merchant understands when they will get paid.
 *
 * If the merchant has no schedule configured, renders nothing.
 */
const DepositSchedule: React.FC< DepositScheduleProps > = ( {
	depositsSchedule,
} ) => {
	// Bail if the merchant is on manual or ad-hoc deposits.
	if ( ! hasAutomaticScheduledDeposits( depositsSchedule.interval ) ) {
		return null;
	}

	const nextDepositHelpContent = (
		<>
			{ __(
				'Deposits are initiated based on the following criteria:',
				'woocommerce-payments'
			) }
			<ul>
				<li>
					{ interpolateComponents( {
						mixedString: __(
							'The {{link}}pending period{{/link}} in your country',
							'woocommerce-payments'
						),
						components: {
							link: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									rel="external noopener noreferrer"
									target="_blank"
									href={
										'https://woocommerce.com/document/woopayments/deposits/deposit-schedule/#pending-period-chart'
									}
								/>
							),
						},
					} ) }
				</li>
				<li>
					{ interpolateComponents( {
						mixedString: __(
							"Your account's {{link}}available funds{{/link}}",
							'woocommerce-payments'
						),
						components: {
							link: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									rel="external noopener noreferrer"
									target="_blank"
									href={
										'https://woocommerce.com/document/woopayments/deposits/deposit-schedule/#available-funds'
									}
								/>
							),
						},
					} ) }
				</li>
				<li>
					{ interpolateComponents( {
						mixedString: __(
							'Your {{link}}deposit schedule{{/link}} settings',
							'woocommerce-payments'
						),
						components: {
							link: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									rel="external noopener noreferrer"
									target="_blank"
									href={
										'https://woocommerce.com/document/woopayments/deposits/change-deposit-schedule/'
									}
								/>
							),
						},
					} ) }
				</li>
			</ul>
		</>
	);

	return (
		<>
			<DepositScheduleSummary depositsSchedule={ depositsSchedule } />
			<ClickTooltip
				content={ nextDepositHelpContent }
				buttonIcon={ <HelpOutlineIcon /> }
				buttonLabel={ 'Deposit schedule tooltip' }
			/>
		</>
	);
};

export default DepositSchedule;
