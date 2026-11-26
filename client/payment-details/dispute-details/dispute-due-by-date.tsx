/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import clsx from 'clsx';
import moment from 'moment';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';

const DisputeDueByDate: React.FC< {
	dueBy: number;
	showRemainingDays?: boolean;
	/** Count down to an inquiry's resolution, which the merchant can't respond to. */
	isResolutionWindow?: boolean;
} > = ( { dueBy, showRemainingDays = true, isResolutionWindow = false } ) => {
	const daysRemaining = Math.floor(
		moment.unix( dueBy ).utc().diff( moment().utc(), 'days', true )
	);
	const respondByDate = formatDateTimeFromTimestamp( dueBy, {
		customFormat: 'F j, Y g:i A',
	} );
	return (
		<span className="dispute-steps__steps__response-date">
			{ respondByDate }
			{ showRemainingDays && (
				// Make it red regardless how many days are left
				<span
					className={ clsx(
						'dispute-steps__steps__response-date--urgent'
					) }
				>
					{ daysRemaining > 0 &&
						! isResolutionWindow &&
						sprintf(
							// Translators: %d is the number of days left to respond to the dispute.
							_n(
								' (%d day left to respond)',
								' (%d days left to respond)',
								daysRemaining,
								'woocommerce-payments'
							),
							daysRemaining
						) }
					{ daysRemaining > 0 &&
						isResolutionWindow &&
						sprintf(
							// Translators: %d is the number of days left to resolve the inquiry.
							_n(
								' (%d day left)',
								' (%d days left)',
								daysRemaining,
								'woocommerce-payments'
							),
							daysRemaining
						) }

					{ daysRemaining === 0 &&
						__( ' (Last day today)', 'woocommerce-payments' ) }
					{ daysRemaining < 0 &&
						__( ' (Past due)', 'woocommerce-payments' ) }
				</span>
			) }
		</span>
	);
};

export default DisputeDueByDate;
