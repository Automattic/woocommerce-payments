/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import strings from '../strings';
import { getAdminUrl } from 'wcpay/utils';

export const getAddApmsTask = (): TaskItemProps | null => {
	const handleClick = () => {
		window.location.href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/additional-payment-methods',
		} );
	};

	return {
		key: 'add-apms',
		level: 3,
		content: '',
		title: strings.tasks.add_apms.title,
		additionalInfo: strings.tasks.add_apms.description,
		completed: false,
		onClick: handleClick,
		action: handleClick,
		expandable: false,
		showActionButton: false,
	};
};
