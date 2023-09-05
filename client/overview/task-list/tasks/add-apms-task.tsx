/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import strings from '../strings';

export const getAddApmsTask = (): TaskItemProps | null => {
	const handleClick = () => {
		window.location.href = 'http://google.com';
	};

	return {
		key: 'add-apms',
		level: 1,
		content: '',
		title: strings.tasks.add_apms.title,
		additionalInfo: strings.tasks.add_apms.description,
		completed: false,
		onClick: handleClick,
		action: handleClick,
		actionLabel: strings.tasks.add_apms.action_label,
		expandable: true,
		expanded: true,
		showActionButton: true,
	};
};
