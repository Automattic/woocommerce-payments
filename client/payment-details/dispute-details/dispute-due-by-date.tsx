/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import classNames from 'classnames';
import moment from 'moment';

const DisputeDueByDate: React.FC< {
	dueBy: number;
	showRemainingDays?: boolean;
} > = ( { dueBy, showRemainingDays = true } ) => {
	const daysRemaining = Math.floor(
		moment.unix( dueBy ).diff( moment(), 'days', true )
	);
	const respondByDate = dateI18n(
		'M j, Y, g:ia',
		moment( dueBy * 1000 ).toISOString()
	);
	return (
		<span className="dispute-steps__steps__response-date">
			{ respondByDate }
			{ showRemainingDays && (
				<span
					className={ classNames( {
						'dispute-steps__steps__response-date--urgent':
							daysRemaining < 3,
						'dispute-steps__steps__response-date--warning':
							daysRemaining < 7 && daysRemaining > 2,
					} ) }
				>
					{ daysRemaining > 0 &&
						sprintf(
							// Translators: %d is the number of days left to respond to the dispute.
							_n(
								'(%d day left to respond)',
								'(%d days left to respond)',
								daysRemaining,
								'woocommerce-payments'
							),
							daysRemaining
						) }

					{ daysRemaining === 0 &&
						__( '(Last day today)', 'woocommerce-payments' ) }
					{ daysRemaining < 0 &&
						__( '(Past due)', 'woocommerce-payments' ) }
				</span>
			) }
		</span>
	);
};

export default DisputeDueByDate;
