/** @format **/

/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { recordTaskEvent } from './record-task-event';

const runReconnectWpcomTaskAction = ( wpcomReconnectUrl ) => {
	recordTaskEvent( 'wcpay_overview_task_click', {
		task: 'reconnect-wpcom',
		source: 'wcpay-reconnect-wpcom-task',
	} );

	window.location.href = addQueryArgs( wpcomReconnectUrl, {
		from: 'WCPAY_OVERVIEW',
		source: 'wcpay-reconnect-wpcom-user-task',
	} );
};

export default runReconnectWpcomTaskAction;
