/**
 * External dependencies
 */
import React, { useMemo } from 'react';

import { ChildTaskContext } from './child-context';
import { useSetupTasksControllerContext } from './setup-context';

const ChildTaskController = ( { children, id = '' } ) => {
	const {
		setActiveTask,
		setCompletedTasks,
		activeTask,
		completedTasks,
	} = useSetupTasksControllerContext();

	const contextValue = useMemo(
		() => ( {
			isActive: id === activeTask,
			setActive: () => setActiveTask( id ),
			setCompleted: ( nextTask ) => {
				setCompletedTasks( ( tasks ) => [ ...tasks, id ] );

				if ( nextTask ) {
					setActiveTask( nextTask );
				}
			},
			isCompleted: completedTasks.includes( id ),
		} ),
		[ setActiveTask, setCompletedTasks, activeTask, completedTasks, id ]
	);

	return (
		<ChildTaskContext.Provider value={ contextValue }>
			{ children }
		</ChildTaskContext.Provider>
	);
};

export default ChildTaskController;
