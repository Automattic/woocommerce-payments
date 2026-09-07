/**
 * External dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import { getAdminUrl } from 'wcpay/utils';
import { recordEvent } from 'tracks';
import type { ActiveEarlyFraudWarning } from 'wcpay/data/early-fraud-warnings/types';

const taskKeyPrefix = 'early-fraud-warning-task-';

// Charge IDs carry no hyphen, so joining on one keeps the charges recoverable.
const taskKeySeparator = '-';

const buildTaskKey = ( chargeIds: string[] ): string =>
	taskKeyPrefix + [ ...chargeIds ].sort().join( taskKeySeparator );

/**
 * The charges a merchant has already dismissed, read back out of the keys the task
 * list stored for them.
 *
 * Dismissal has to survive the task's own key changing, because the key names the
 * warnings the task covered at the time. Without this, resolving one of two dismissed
 * warnings would leave a key nothing matches and bring the other one back.
 *
 * @param dismissedTasks Keys the merchant has dismissed, ours and everything else's.
 */
const getDismissedChargeIds = ( dismissedTasks: string[] ): Set< string > =>
	new Set(
		dismissedTasks
			.filter( ( key ) => key.startsWith( taskKeyPrefix ) )
			.flatMap( ( key ) =>
				key.slice( taskKeyPrefix.length ).split( taskKeySeparator )
			)
	);

export const getEarlyFraudWarningTask = (
	/**
	 * Orders whose latest early fraud warning is still actionable, as exposed
	 * by wcpaySettings.activeEarlyFraudWarnings.
	 */
	activeEarlyFraudWarnings: ActiveEarlyFraudWarning[],
	/**
	 * Task keys the merchant has dismissed, from
	 * wcpaySettings.overviewTasksVisibility.dismissedTodoTasks.
	 */
	dismissedTasks: string[] = []
): TaskItemProps | null => {
	const dismissedChargeIds = getDismissedChargeIds( dismissedTasks );
	const warnings = activeEarlyFraudWarnings.filter(
		( warning ) => ! dismissedChargeIds.has( warning.charge_id )
	);
	const warningCount = warnings.length;

	if ( warningCount === 0 ) {
		return null;
	}

	const handleClick = () => {
		recordEvent( 'wcpay_overview_task_click', {
			task: 'early-fraud-warning-task',
			active_early_fraud_warning_count: warningCount,
		} );
		const history = getHistory();
		if ( warningCount === 1 ) {
			// Land directly on the affected payment when there is only one.
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: warnings[ 0 ].charge_id,
				} )
			);
		} else {
			// The list has no early-fraud-warning filter yet, so this lands unfiltered.
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
				} )
			);
		}
	};

	return {
		// Key the task by the affected charges so a new warning renders a fresh task.
		key: buildTaskKey( warnings.map( ( warning ) => warning.charge_id ) ),
		title: sprintf(
			// The singular form takes no placeholder; sprintf leaves it untouched.
			_n(
				'Review a payment flagged for potential fraud',
				'Review %d payments flagged for potential fraud',
				warningCount,
				'woocommerce-payments'
			),
			warningCount
		),
		content:
			warningCount === 1
				? __(
						'Refunding it now can prevent a dispute.',
						'woocommerce-payments'
				  )
				: __(
						'Refunding flagged payments now can prevent disputes.',
						'woocommerce-payments'
				  ),
		level: 2,
		completed: false,
		expanded: true,
		expandable: true,
		// A merchant who has looked at a flagged payment and decided against refunding
		// has no other way to clear the task: the warning only resolves on a full refund
		// or a platform update, and neither is theirs to trigger.
		isDismissable: true,
		showActionButton: true,
		actionLabel:
			warningCount === 1
				? __( 'Review payment', 'woocommerce-payments' )
				: __( 'See transactions', 'woocommerce-payments' ),
		action: handleClick,
		onClick: () => {
			// Only handle clicks on the action button.
		},
	};
};
