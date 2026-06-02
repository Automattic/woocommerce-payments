/** @format **/

/**
 * Internal dependencies.
 */
import getReconnectWpcomTask from './tasks/reconnect-task';
import getUpdateBusinessDetailsTask from './tasks/update-business-details-task';
import { TaskItemProps } from './types';
import getGoLiveTask from './tasks/go-live-task';

// Requirements we don't want to show to the user because they are too generic/not useful. These refer to Stripe error codes.
const requirementBlacklist = [ 'invalid_value_other' ];

interface AdminTaskListProps {
	showUpdateDetailsTask: boolean;
	wpcomReconnectUrl: string;
	showGoLiveTask: boolean;
}

const getAdminOnboardingTasks = ( {
	showUpdateDetailsTask,
	wpcomReconnectUrl,
	showGoLiveTask = false,
}: AdminTaskListProps ): TaskItemProps[] => {
	const {
		status,
		currentDeadline,
		pastDue,
		accountLink,
		requirements,
		detailsSubmitted,
	} = wcpaySettings.accountStatus;

	// Filter out requirements that we don't want to show to the user.
	const requirementErrors = requirements?.errors?.filter(
		( error ) => ! requirementBlacklist.includes( error.code )
	);

	const tasks: TaskItemProps[] = [];

	if ( showUpdateDetailsTask ) {
		const updateBusinessDetailsTask = getUpdateBusinessDetailsTask(
			requirementErrors ?? [],
			status ?? '',
			accountLink ?? '',
			Number( currentDeadline ) ?? null,
			pastDue ?? false,
			detailsSubmitted ?? true
		);

		if ( updateBusinessDetailsTask ) {
			tasks.push( updateBusinessDetailsTask );
		}
	}

	if ( wpcomReconnectUrl ) {
		const reconnectTask = getReconnectWpcomTask( wpcomReconnectUrl );
		if ( reconnectTask ) {
			tasks.push( reconnectTask );
		}
	}

	if (
		showGoLiveTask &&
		wcpaySettings.isAccountConnected &&
		wcpaySettings?.testModeOnboarding
	) {
		const goLiveTask = getGoLiveTask();
		if ( goLiveTask ) {
			tasks.push( goLiveTask );
		}
	}

	return tasks;
};

export default getAdminOnboardingTasks;
