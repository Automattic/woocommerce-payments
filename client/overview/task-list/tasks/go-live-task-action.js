/** @format **/

/**
 * Internal dependencies
 */
import { renderSetupLivePaymentsModal } from './go-live-modal-loader';
import { recordTaskEvent } from './record-task-event';

const runGoLiveTaskAction = () => {
	recordTaskEvent( 'wcpay_overview_task_click', {
		task: 'go-live',
		source: 'wcpay-go-live-task',
	} );

	renderSetupLivePaymentsModal();
};

export default runGoLiveTaskAction;
