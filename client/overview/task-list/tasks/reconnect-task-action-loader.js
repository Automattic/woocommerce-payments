/** @format **/

export const runReconnectWpcomTaskAction = async ( wpcomReconnectUrl ) => {
	( await import( './reconnect-task-action' ) ).default( wpcomReconnectUrl );
};
