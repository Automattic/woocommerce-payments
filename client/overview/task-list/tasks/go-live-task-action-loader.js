/** @format **/

export const runGoLiveTaskAction = async () => {
	( await import( './go-live-task-action' ) ).default();
};
