/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { TaskItem } from '@woocommerce/experimental';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type { CachedDispute } from 'wcpay/types/disputes';
import { formatCurrency } from 'wcpay/utils/currency';
import { getAdminUrl } from 'wcpay/utils';
import wcpayTracks from 'wcpay/tracks';

interface TaskItemProps extends React.ComponentProps< typeof TaskItem > {
	key: string;
}

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
		window.location.href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/disputes',
			filter: 'awaiting_response',
		} );
	};

	const disputeTask: TaskItemProps = {
		key: 'dispute-resolution-task',
		title: '',
		level: 2, // TODO: add dynamic level here
		completed: false,
		content: '', // TODO: add task subtitle here
		expanded: true,
		showActionButton: true,
		actionLabel: __( 'Respond now', 'woocommerce-payments' ),
		action: handleClick,
		onClick: handleClick,
	};

	if ( disputeCount === 1 ) {
		const dispute = activeDisputes[ 0 ];
		const now = moment();
		const dueBy = moment( dispute.due_by );

		const dueWithin24h =
			dueBy.diff( now, 'hours' ) > 0 && dueBy.diff( now, 'hours' ) <= 24;

		if ( dueWithin24h ) {
			// If the dispute is due within 24 hours, show a more urgent message.
			const title = sprintf(
				__(
					'Respond to a dispute for %s – last day',
					'woocommerce-payments'
				),
				formatCurrency( dispute.amount, dispute.currency )
			);
			disputeTask.title = title;
			return disputeTask;
		}

		disputeTask.title = sprintf(
			__( 'Respond to a dispute for %s', 'woocommerce-payments' ),
			formatCurrency( dispute.amount, dispute.currency )
		);
		return disputeTask;
	}

	// If there are multiple disputes, calculate the count and total amount per currency.
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

	disputeTask.title = sprintf(
		__(
			'Respond to %d active disputes for a total of %s',
			'woocommerce-payments'
		),
		disputeCount,
		disputeTotalAmounts
	);
	return disputeTask;
};
