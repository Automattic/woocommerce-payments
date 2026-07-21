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

export interface ActiveEarlyFraudWarning {
	order_id: number;
	charge_id: string;
	created: number;
}

export const getEarlyFraudWarningTask = (
	/**
	 * Orders whose latest early fraud warning is still actionable, as exposed
	 * by wcpaySettings.activeEarlyFraudWarnings.
	 */
	activeEarlyFraudWarnings: ActiveEarlyFraudWarning[]
): TaskItemProps | null => {
	const warningCount = activeEarlyFraudWarnings.length;

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
					id: activeEarlyFraudWarnings[ 0 ].charge_id,
				} )
			);
		} else {
			// The list has no early-fraud-warning filter yet; the warning
			// chips make the affected rows stand out.
			history.push(
				getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions',
				} )
			);
		}
	};

	// Key the task by the affected charges so a new warning renders a fresh task.
	const taskKey = `early-fraud-warning-task-${ activeEarlyFraudWarnings
		.map( ( warning ) => warning.charge_id )
		.join( '-' ) }`;

	return {
		key: taskKey,
		title:
			warningCount === 1
				? __(
						'Review a payment flagged for potential fraud',
						'woocommerce-payments'
				  )
				: sprintf(
						__(
							'Review %d payments flagged for potential fraud',
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
		isDismissable: false,
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
