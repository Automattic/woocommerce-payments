/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';

export const getReconnectWpcomTask = (
	wpcomReconnectUrl: string
): TaskItemProps | null => {
	const handleClick = () => {
		window.location.href = wpcomReconnectUrl;
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
