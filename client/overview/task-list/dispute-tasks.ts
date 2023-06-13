/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from './types';
import type { CachedDispute } from 'wcpay/types/disputes';
import { formatCurrency } from 'wcpay/utils/currency';
import { getAdminUrl } from 'wcpay/utils';
import wcpayTracks from 'wcpay/tracks';

/**
 * Returns true if the dispute is due within the specified number of days.
 *
 * @param {CachedDispute} dispute - The dispute to check.
 * @param {number} [days] - The number of days to check. Defaults to 1.
 *
 * @return {boolean} True if the dispute is due within the specified number of days.
 */
const isDueWithin = ( dispute: CachedDispute, days = 1 ) => {
	const now = moment();
	const dueBy = moment( dispute.due_by );
	return (
		dueBy.diff( now, 'hours' ) > 0 &&
		dueBy.diff( now, 'hours' ) <= 24 * days
	);
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
	const disputeCount = activeDisputes.length;

	if ( disputeCount === 0 ) {
		return null;
	}

	const handleClick = () => {
		wcpayTracks.recordEvent( 'wcpay_overview_task', {
			task: 'dispute-resolution-task',
		} );
		if ( disputeCount === 1 ) {
			// Redirect to the dispute details page if there is only one dispute.
			const disputeId = activeDisputes[ 0 ].dispute_id;
			window.location.href = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes/details',
				id: disputeId,
			} );
		} else {
			window.location.href = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
				filter: 'awaiting_response',
			} );
		}
	};

	// Create a new array and sort by `due_by` ascending.
	activeDisputes = [ ...activeDisputes ].sort( ( a, b ) =>
		moment( a.due_by ).diff( moment( b.due_by ) )
	);

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
		onClick: handleClick,
		dataAttrs: {
			'data-urgent': !! ( numDisputesDueWithin24h >= 1 ),
		},
	};

	// Single dispute.
	if ( disputeCount === 1 ) {
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
						// Show due_by time in local time.
						moment( dispute.due_by ).format( 'h:mm A' ) // E.g. "11:59 PM".
				  )
				: sprintf(
						__(
							'By %s – %s left to respond',
							'woocommerce-payments'
						),
						moment( dispute.due_by ).format( 'MMM D, YYYY' ), // E.g. "Jan 1, 2021".
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
		disputeCount,
		disputeTotalAmounts
	);
	disputeTask.content =
		// Final day / Last week to respond for N of the disputes
		numDisputesDueWithin24h >= 1
			? sprintf(
					__(
						'Final day to respond for %d of the disputes',
						'woocommerce-payments'
					),
					numDisputesDueWithin24h
			  )
			: sprintf(
					__(
						'Last week to respond for %d of the disputes',
						'woocommerce-payments'
					),
					numDisputesDueWithin7Days
			  );

	disputeTask.actionLabel = __( 'See disputes', 'woocommerce-payments' );

	return disputeTask;
};
