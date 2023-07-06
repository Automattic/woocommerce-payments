/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import type { CachedDispute } from 'wcpay/types/disputes';
import { formatCurrency } from 'wcpay/utils/currency';
import { getAdminUrl } from 'wcpay/utils';
import wcpayTracks from 'wcpay/tracks';

/**
 * Returns true if the dispute is due within the specified number of days.
 * Returns false if the dispute is not due within the specified number of days
 * or if the due_by value is an empty string.
 *
 * @param {CachedDispute} dispute - The dispute to check.
 * @param {number} days - The number of days to check. Defaults to 1.
 *
 * @return {boolean} True if the dispute is due within the specified number of days.
 */
const isDueWithin = ( dispute: CachedDispute, days: number ) => {
	if ( dispute.due_by === '' ) {
		return false;
	}
	// Get current time in UTC.
	const now = moment().utc();
	const dueBy = moment.utc( dispute.due_by );
	return dueBy.diff( now, 'hours' ) > 0 && dueBy.diff( now, 'days' ) <= days;
};

/**
 * Returns an array of disputes that are due within the specified number of days.
 *
 * @param {CachedDispute[]} activeDisputes - The active disputes to filter.
 * @param {number} days - The number of days to check.
 *
 * @return {CachedDispute[]} The disputes that are due within the specified number of days.
 */
export const getDisputesDueWithinDays = (
	activeDisputes: CachedDispute[],
	days: number
): CachedDispute[] =>
	activeDisputes.filter( ( dispute ) => isDueWithin( dispute, days ) );

export const getDisputeResolutionTask = (
	/**
	 * Active disputes (awaiting a response) to generate the notice string for.
	 */
	activeDisputes: CachedDispute[]
): TaskItemProps | null => {
	// Create a new array and sort by `due_by` ascending.
	activeDisputes = [ ...activeDisputes ]
		.filter( ( dispute ) => dispute.due_by !== '' )
		.sort( ( a, b ) => moment( a.due_by ).diff( moment( b.due_by ) ) );

	const activeDisputeCount = activeDisputes.length;

	if ( activeDisputeCount === 0 ) {
		return null;
	}

	const handleClick = () => {
		wcpayTracks.recordEvent( wcpayTracks.events.OVERVIEW_TASK_CLICK, {
			task: 'dispute-resolution-task',
			active_dispute_count: activeDisputeCount,
		} );
		const history = getHistory();
		if ( activeDisputeCount === 1 ) {
			// Redirect to the dispute details page if there is only one dispute.
			const disputeId = activeDisputes[ 0 ].dispute_id;
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/disputes/details',
					id: disputeId,
				} )
			);
		} else {
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/disputes',
					filter: 'awaiting_response',
				} )
			);
		}
	};

	const numDisputesDueWithin24h = getDisputesDueWithinDays(
		activeDisputes,
		1
	).length;

	// Create a unique key for each combination of dispute IDs
	// to ensure the task is rendered if a previous task was dismissed.
	const disputeTaskKey = `dispute-resolution-task-${ activeDisputes
		.map( ( dispute ) => dispute.dispute_id )
		.join( '-' ) }`;

	const disputeTask: TaskItemProps = {
		key: disputeTaskKey,
		title: '', // Title text defined below.
		content: '', // Subtitle text defined below.
		level: 1,
		completed: false,
		expanded: true,
		isDismissable: false,
		showActionButton: true,
		actionLabel: __( 'Respond now', 'woocommerce-payments' ),
		action: handleClick,
		onClick: () => {
			// Only handle clicks on the action button.
		},
		dataAttrs: {
			'data-urgent': !! ( numDisputesDueWithin24h >= 1 ),
		},
	};

	// Single dispute.
	if ( activeDisputeCount === 1 ) {
		const dispute = activeDisputes[ 0 ];
		const amountFormatted = formatCurrency(
			dispute.amount,
			dispute.currency
		);

		disputeTask.title =
			numDisputesDueWithin24h >= 1
				? sprintf(
						__(
							'Respond to a dispute for %s – Last day',
							'woocommerce-payments'
						),
						amountFormatted
				  )
				: sprintf(
						__(
							'Respond to a dispute for %s',
							'woocommerce-payments'
						),
						amountFormatted
				  );

		disputeTask.content =
			numDisputesDueWithin24h >= 1
				? sprintf(
						__( 'Respond today by %s', 'woocommerce-payments' ),
						// Show due_by time in local timezone: e.g. "11:59 PM".
						dateI18n(
							'g:i A',
							moment.utc( dispute.due_by ).local().toISOString()
						)
				  )
				: sprintf(
						__(
							'By %s – %s left to respond',
							'woocommerce-payments'
						),
						// Show due_by date in local timezone: e.g. "Jan 1, 2021".
						dateI18n(
							'M j, Y',
							moment.utc( dispute.due_by ).local().toISOString()
						),
						moment( dispute.due_by ).fromNow( true ) // E.g. "2 days".
				  );

		return disputeTask;
	}

	// Multiple disputes.
	// Calculate the count and total amount per currency.
	const disputeTotalPerCurrency = activeDisputes.reduce(
		( totalPerCurrency, dispute ) => {
			const { currency, amount } = dispute;
			const total = totalPerCurrency[ currency ] || 0;

			return {
				...totalPerCurrency,
				[ currency ]: total + amount,
			};
		},
		{} as Record< string, number >
	);

	// Generate a formatted total amount for each currency: "€10.00, $20.00".
	const disputeTotalAmounts: string = Object.entries(
		disputeTotalPerCurrency
	)
		.map( ( [ currency, amount ] ) => formatCurrency( amount, currency ) )
		.join( ', ' );

	const numDisputesDueWithin7Days = getDisputesDueWithinDays(
		activeDisputes,
		7
	).length;

	disputeTask.title = sprintf(
		__(
			'Respond to %d active disputes for a total of %s',
			'woocommerce-payments'
		),
		activeDisputeCount,
		disputeTotalAmounts
	);
	disputeTask.content =
		// Final day / Last week to respond to N of the disputes
		numDisputesDueWithin24h >= 1
			? sprintf(
					__(
						'Final day to respond to %d of the disputes',
						'woocommerce-payments'
					),
					numDisputesDueWithin24h
			  )
			: sprintf(
					__(
						'Last week to respond to %d of the disputes',
						'woocommerce-payments'
					),
					numDisputesDueWithin7Days
			  );

	disputeTask.actionLabel = __( 'See disputes', 'woocommerce-payments' );

	return disputeTask;
};
