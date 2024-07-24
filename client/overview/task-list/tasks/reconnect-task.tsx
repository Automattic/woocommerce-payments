/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import { recordEvent } from 'wcpay/tracks';
import { addQueryArgs } from '@wordpress/url';

export const getReconnectWpcomTask = (
	wpcomReconnectUrl: string
): TaskItemProps | null => {
	const handleClick = () => {
		recordEvent( 'wcpay_overview_task_click', {
			task: 'reconnect-wpcom',
			source: 'wcpay-reconnect-wpcom-task',
		} );

		window.location.href = addQueryArgs( wpcomReconnectUrl, {
			from: 'WCPAY_OVERVIEW',
			source: 'wcpay-reconnect-wpcom-user-task',
		} );
	};

	return {
		key: 'reconnect-wpcom-user',
		level: 1,
		content: '',
		title: sprintf(
			/* translators: %s: WooPayments */
			__( 'Reconnect %s', 'woocommerce-payments' ),
			'WooPayments'
		),
		additionalInfo: sprintf(
			/* translators: %s: WooPayments */
			__(
				'%s is missing a connected WordPress.com account. Some functionality will be limited without a connected account.',
				'woocommerce-payments'
			),
			'WooPayments'
		),
		completed: false,
		onClick: handleClick,
		action: handleClick,
		actionLabel: __( 'Reconnect', 'woocommerce-payments' ),
		expandable: true,
		expanded: true,
		showActionButton: true,
	};
};
