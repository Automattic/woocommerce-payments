/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import { getAdminUrl } from 'wcpay/utils';
import { recordEvent } from 'tracks';
import type { ActiveEarlyFraudWarning } from 'wcpay/data/early-fraud-warnings/types';

const taskKeyPrefix = 'early-fraud-warning-task-';

const buildTaskKey = ( chargeId: string ): string => taskKeyPrefix + chargeId;

const getOrderNumber = ( warning: ActiveEarlyFraudWarning ): string =>
	warning.order_number || String( warning.order_id );

const buildEarlyFraudWarningTask = (
	warning: ActiveEarlyFraudWarning,
	activeEarlyFraudWarningCount: number
): TaskItemProps => {
	const orderNumber = getOrderNumber( warning );

	const handleClick = () => {
		recordEvent( 'wcpay_overview_task_click', {
			task: 'early-fraud-warning-task',
			active_early_fraud_warning_count: activeEarlyFraudWarningCount,
		} );
		getHistory().push(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: warning.charge_id,
			} )
		);
	};

	return {
		key: buildTaskKey( warning.charge_id ),
		title: sprintf(
			/* translators: %s: order number, e.g. 12 or INV-42 */
			__(
				'Review order #%s flagged for potential fraud',
				'woocommerce-payments'
			),
			orderNumber
		),
		content: __(
			'Refunding it now can prevent a dispute.',
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
		actionLabel: __( 'Review payment', 'woocommerce-payments' ),
		action: handleClick,
		onClick: () => {
			// Only handle clicks on the action button.
		},
	};
};

export const getEarlyFraudWarningTasks = (
	/**
	 * Orders whose latest early fraud warning is still actionable.
	 */
	activeEarlyFraudWarnings: ActiveEarlyFraudWarning[]
): TaskItemProps[] => {
	const activeEarlyFraudWarningCount = activeEarlyFraudWarnings.length;

	return activeEarlyFraudWarnings.map( ( warning ) =>
		buildEarlyFraudWarningTask( warning, activeEarlyFraudWarningCount )
	);
};
