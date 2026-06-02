/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import {
	getDisputeResolutionTask,
	getDisputesDueWithinDays,
} from './tasks/dispute-task';
import getReconnectWpcomTask from './tasks/reconnect-task';
import getUpdateBusinessDetailsTask from './tasks/update-business-details-task';
import { CachedDispute } from 'wcpay/types/disputes';
import { TaskItemProps } from './types';
import getGoLiveTask from './tasks/go-live-task';

const isInTestModeOnboarding = ( fallback = false ): boolean =>
	!! wcpaySettings?.testModeOnboarding || fallback;

// Requirements we don't want to show to the user because they are too generic/not useful. These refer to Stripe error codes.
const requirementBlacklist = [ 'invalid_value_other' ];

interface TaskListProps {
	showUpdateDetailsTask: boolean;
	wpcomReconnectUrl: string;
	activeDisputes?: CachedDispute[];
	showGoLiveTask: boolean;
}

export const getTasks = ( {
	showUpdateDetailsTask,
	wpcomReconnectUrl,
	activeDisputes = [],
	showGoLiveTask = false,
}: TaskListProps ): TaskItemProps[] => {
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

	const isUpdateDetailsTaskVisible = showUpdateDetailsTask;

	const isDisputeTaskVisible =
		!! activeDisputes &&
		// Only show the dispute task if there are disputes due within 7 days.
		getDisputesDueWithinDays( activeDisputes, 7 ).length > 0;

	const isGoLiveTaskVisible =
		wcpaySettings.isAccountConnected &&
		isInTestModeOnboarding( false ) &&
		showGoLiveTask;

	return [
		isUpdateDetailsTaskVisible &&
			getUpdateBusinessDetailsTask(
				requirementErrors ?? [],
				status ?? '',
				accountLink ?? '',
				Number( currentDeadline ) ?? null,
				pastDue ?? false,
				detailsSubmitted ?? true
			),
		wpcomReconnectUrl && getReconnectWpcomTask( wpcomReconnectUrl ),
		isDisputeTaskVisible && getDisputeResolutionTask( activeDisputes ),
		isGoLiveTaskVisible && getGoLiveTask(),
	]
		.filter( Boolean )
		.filter( ( task ) => task !== null ) as TaskItemProps[];
};

export const taskSort = ( a: TaskItemProps, b: TaskItemProps ): number => {
	if ( a.completed || b.completed ) {
		return a.completed ? 1 : -1;
	}
	// Three is the lowest level.
	const aLevel = a.level || 3;
	const bLevel = b.level || 3;
	if ( aLevel === bLevel ) {
		return 0;
	}
	return aLevel > bLevel ? 1 : -1;
};
