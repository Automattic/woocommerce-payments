/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';

/**
 * The type of the props for the DepositScheduleDescription component.
 * Mimics the AccountOverview.Account['deposits_schedule'] declaration.
 */
type DepositsScheduleProps = AccountOverview.Account[ 'deposits_schedule' ];

/**
 * Renders the Deposit Schedule details component.
 *
 * eg "Your deposits are dispatched automatically every day"
 *
 * @param {DepositsScheduleProps} depositsSchedule The account's deposit schedule.
 * @return {JSX.Element} Rendered element with Deposit Schedule details.
 */
const DepositSchedule: React.FC< DepositsScheduleProps > = (
	depositsSchedule: DepositsScheduleProps
): JSX.Element => {
	switch ( depositsSchedule.interval ) {
		case 'daily':
			return interpolateComponents( {
				/** translators: {{strong}}: placeholders are opening and closing strong tags. */
				mixedString: __(
					'Your deposits are dispatched {{strong}}automatically every day{{/strong}}',
					'woocommerce-payments'
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
					/** translators: %s: is the day of the week. eg "Friday". {{strong}}: placeholders are opening and closing strong tags.*/
					__(
						'Your deposits are dispatched {{strong}}automatically every %s{{/strong}}',
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
					/** translators: {{strong}}: placeholders are opening and closing strong tags. */
					mixedString: __(
						'Your deposits are dispatched {{strong}}automatically on the last day of every month{{/strong}}',
						'woocommerce-payments'
					),
					components: {
						strong: <strong />,
					},
				} );
			}

			return interpolateComponents( {
				mixedString: sprintf(
					/** translators: %s: is the day of the month. eg "15th". {{strong}}: placeholders are opening and closing strong tags.*/
					__(
						'Your deposits are dispatched {{strong}}automatically on the %s of every month{{/strong}}',
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

export default DepositSchedule;
