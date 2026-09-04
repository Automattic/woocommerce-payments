/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import type { CachedDispute, DisputesSummaryData } from 'wcpay/types/disputes';
import { formatCurrency } from 'multi-currency/interface/functions';
import { getAdminUrl } from 'wcpay/utils';
import { recordEvent } from 'tracks';
import { isDueWithin } from 'wcpay/disputes/utils';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';

export const getDisputeResolutionTask = (
	/**
	 * Active disputes (awaiting a response) to generate the notice string for.
	 */
	activeDisputes: CachedDispute[],
	activeDisputesSummary?: DisputesSummaryData
): TaskItemProps | null => {
	let disputesWithDeadlines: CachedDispute[] | undefined;
	const getDisputesWithDeadlines = (): CachedDispute[] => {
		disputesWithDeadlines ??= [ ...activeDisputes ]
			.filter( ( dispute ) => dispute.due_by !== '' )
			.sort( ( a, b ) => moment( a.due_by ).diff( moment( b.due_by ) ) );

		return disputesWithDeadlines;
	};

	const fallbackDisputes =
		activeDisputesSummary?.count === undefined
			? getDisputesWithDeadlines()
			: activeDisputes;
	const activeDisputeCount =
		activeDisputesSummary?.count ?? fallbackDisputes.length;
	const hasSummaryDeadline =
		activeDisputesSummary !== undefined &&
		Object.prototype.hasOwnProperty.call(
			activeDisputesSummary,
			'earliest_due_by'
		);
	const earliestDueBy = hasSummaryDeadline
		? activeDisputesSummary.earliest_due_by
		: getDisputesWithDeadlines()[ 0 ]?.due_by;

	if (
		activeDisputeCount === 0 ||
		! earliestDueBy ||
		! isDueWithin( { dueBy: earliestDueBy, days: 7 } )
	) {
		return null;
	}

	const summaryAmounts = activeDisputesSummary?.amount_by_currency;
	let amountEntries =
		summaryAmounts && ! Array.isArray( summaryAmounts )
			? Object.entries( summaryAmounts ).filter(
					( [ currency, amount ] ) =>
						currency !== '' &&
						typeof amount === 'number' &&
						Number.isFinite( amount )
			  )
			: [];

	if (
		amountEntries.length === 0 &&
		fallbackDisputes.length === activeDisputeCount
	) {
		const rowAmounts = fallbackDisputes.reduce( ( amounts, dispute ) => {
			amounts[ dispute.currency ] =
				( amounts[ dispute.currency ] ?? 0 ) + dispute.amount;

			return amounts;
		}, {} as Record< string, number > );
		amountEntries = Object.entries( rowAmounts );
	}

	amountEntries.sort( ( [ currencyA ], [ currencyB ] ) =>
		currencyA.localeCompare( currencyB )
	);
	const canOpenSingleDispute =
		activeDisputeCount === 1 &&
		activeDisputes.length === 1 &&
		!! activeDisputes[ 0 ].charge_id;

	const handleClick = () => {
		recordEvent( 'wcpay_overview_task_click', {
			task: 'dispute-resolution-task',
			active_dispute_count: activeDisputeCount,
		} );
		const history = getHistory();
		if ( canOpenSingleDispute ) {
			// Redirect to the transaction details page if there is only one dispute.
			const chargeId = activeDisputes[ 0 ].charge_id;
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: chargeId,
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

	const isDueToday =
		formatDateTimeFromString( earliestDueBy, {
			customFormat: 'Y-m-d',
		} ) ===
		formatDateTimeFromString(
			moment.utc().format( 'YYYY-MM-DD HH:mm:ss' ),
			{ customFormat: 'Y-m-d' }
		);
	const isDueWithin72h = isDueWithin( { dueBy: earliestDueBy, days: 3 } );

	// Create a unique key for each combination of dispute IDs
	// to ensure the task is rendered if a previous task was dismissed.
	const keyDisputes = hasSummaryDeadline
		? activeDisputes
		: getDisputesWithDeadlines();
	const disputeTaskKey = `dispute-resolution-task-${
		keyDisputes.map( ( dispute ) => dispute.dispute_id ).join( '-' ) ||
		'summary'
	}`;

	const disputeTask: TaskItemProps = {
		key: disputeTaskKey,
		title: '', // Title text defined below.
		content: '', // Subtitle text defined below.
		level: 1,
		completed: false,
		expanded: true,
		expandable: true,
		isDismissable: false,
		showActionButton: true,
		actionLabel: canOpenSingleDispute
			? __( 'Respond now', 'woocommerce-payments' )
			: __( 'See disputes', 'woocommerce-payments' ),
		action: handleClick,
		onClick: () => {
			// Only handle clicks on the action button.
		},
		dataAttrs: {
			'data-urgent': isDueWithin72h,
		},
	};

	if ( activeDisputeCount === 1 && amountEntries.length === 1 ) {
		const [ currency, amount ] = amountEntries[ 0 ];
		const amountFormatted = formatCurrency( amount, currency );

		disputeTask.title = isDueToday
			? sprintf(
					__(
						'Respond to a dispute for %s – Last day',
						'woocommerce-payments'
					),
					amountFormatted
			  )
			: sprintf(
					__( 'Respond to a dispute for %s', 'woocommerce-payments' ),
					amountFormatted
			  );
	} else if ( activeDisputeCount === 1 ) {
		disputeTask.title = isDueToday
			? __(
					'Respond to an active dispute – Last day',
					'woocommerce-payments'
			  )
			: __( 'Respond to an active dispute', 'woocommerce-payments' );
	} else if ( amountEntries.length === 1 ) {
		const [ currency, amount ] = amountEntries[ 0 ];
		disputeTask.title = sprintf(
			__(
				'Respond to %d active disputes for a total of %s',
				'woocommerce-payments'
			),
			activeDisputeCount,
			formatCurrency( amount, currency )
		);
	} else if ( amountEntries.length === 2 ) {
		const formatAmountWithCurrencyCode = ( [ currency, amount ]: [
			string,
			number
		] ): string => {
			const formattedAmount = formatCurrency( amount, currency );
			const currencyCode = currency.toUpperCase();

			return formattedAmount.toUpperCase().includes( currencyCode )
				? formattedAmount
				: `${ formattedAmount } ${ currencyCode }`;
		};
		disputeTask.title = sprintf(
			__(
				'Respond to %1$d active disputes for totals of %2$s and %3$s',
				'woocommerce-payments'
			),
			activeDisputeCount,
			formatAmountWithCurrencyCode( amountEntries[ 0 ] ),
			formatAmountWithCurrencyCode( amountEntries[ 1 ] )
		);
	} else if ( amountEntries.length >= 3 ) {
		disputeTask.title = sprintf(
			__(
				'Respond to %1$d active disputes in %2$d currencies',
				'woocommerce-payments'
			),
			activeDisputeCount,
			amountEntries.length
		);
	} else {
		disputeTask.title = sprintf(
			__( 'Respond to %d active disputes', 'woocommerce-payments' ),
			activeDisputeCount
		);
	}

	disputeTask.content = isDueToday
		? sprintf(
				__( 'Respond today by %s', 'woocommerce-payments' ),
				// Show the deadline time in the local timezone: e.g. "11:59 PM".
				formatDateTimeFromString( earliestDueBy, {
					customFormat: 'g:i A',
				} )
		  )
		: sprintf(
				__( 'By %s – %s left to respond', 'woocommerce-payments' ),
				// Show the deadline date in the local timezone: e.g. "Jan 1, 2021".
				formatDateTimeFromString( earliestDueBy ),
				moment.utc( earliestDueBy ).fromNow( true ) // E.g. "2 days".
		  );

	return disputeTask;
};
