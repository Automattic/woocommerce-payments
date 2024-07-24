/**
 * External dependencies
 */

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
			from: 'WCPAY_OVERVIEW',
			source: 'wcpay-add-apms-task',
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
