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
import type * as AccountOverview from 'wcpay/types/account-overview';

interface DepositScheduleProps {
	depositsSchedule: AccountOverview.Account[ 'deposits_schedule' ];
	showNextDepositDate?: boolean;
}
/**
 * Renders the Deposit Schedule details component.
 *
 * eg "Your deposits are dispatched automatically every day"
 */
const DepositSchedule: React.FC< DepositScheduleProps > = ( {
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

export default DepositSchedule;
