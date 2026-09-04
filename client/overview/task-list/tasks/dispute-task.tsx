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
	 * Summary of active disputes (awaiting a response) for the notice.
	 */
	activeDisputesSummary?: DisputesSummaryData,
	activeDispute?: CachedDispute
): TaskItemProps | null => {
	const activeDisputeCount = activeDisputesSummary?.count ?? 0;
	const earliestDueBy = activeDisputesSummary?.earliest_due_by;

	if (
		activeDisputeCount === 0 ||
		! earliestDueBy ||
		! isDueWithin( { dueBy: earliestDueBy, days: 7 } )
	) {
		return null;
	}

	const summaryAmounts = activeDisputesSummary?.amount_by_currency;
	const amountEntries =
		summaryAmounts && ! Array.isArray( summaryAmounts )
			? Object.entries( summaryAmounts ).filter(
					( [ currency, amount ] ) =>
						currency !== '' &&
						typeof amount === 'number' &&
						Number.isFinite( amount )
			  )
			: [];

	amountEntries.sort( ( [ currencyA ], [ currencyB ] ) =>
		currencyA.localeCompare( currencyB )
	);
	const canOpenSingleDispute =
		activeDisputeCount === 1 && !! activeDispute?.charge_id;

	const handleClick = () => {
		recordEvent( 'wcpay_overview_task_click', {
			task: 'dispute-resolution-task',
			active_dispute_count: activeDisputeCount,
		} );
		const history = getHistory();
		if ( canOpenSingleDispute ) {
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: activeDispute.charge_id,
				} )
			);
			return;
		}

		history.push(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
				filter: 'awaiting_response',
			} )
		);
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

	const disputeTask: TaskItemProps = {
		key: `dispute-resolution-task-${
			activeDispute?.dispute_id ?? 'summary'
		}`,
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
