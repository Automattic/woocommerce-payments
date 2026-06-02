/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import { runGoLiveTaskAction } from './go-live-task-action-loader';

const getGoLiveTask = (): TaskItemProps | null => {
	const handleClick = () => {
		runGoLiveTaskAction();
	};

	return {
		key: 'go-live-payments',
		level: 3,
		content: '',
		title: __( 'Activate payments', 'woocommerce-payments' ),
		time: __( '10 minutes', 'woocommerce-payments' ),
		completed: false,
		onClick: handleClick,
		action: handleClick,
		expandable: false,
		showActionButton: false,
	};
};

export default getGoLiveTask;
