/** @format **/

export const runUpdateBusinessDetailsTaskAction = async ( props ) => {
	( await import( './update-business-details-task-action' ) ).default(
		props
	);
};
