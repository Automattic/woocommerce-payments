/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

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
		title: __( 'Reconnect WooCommerce Payments', 'woocommerce-payments' ),
		additionalInfo: __(
			'WooCommerce Payments is missing a connected WordPress.com account. ' +
				'Some functionality will be limited without a connected account.',
			'woocommerce-payments'
		),
		completed: false,
		onClick: () => {
			// Only handle clicks on the action button.
		},
		action: handleClick,
		actionLabel: __( 'Reconnect', 'woocommerce-payments' ),
		expandable: true,
		expanded: true,
		showActionButton: true,
	};
};
