/**
 * External dependencies
 */
import React, { useState, useMemo } from 'react';

import { SetupTasksControllerContext } from './setup-context';

const SetupTasksController = ( {
	children,
	defaultActiveTask = '',
	defaultCompletedTasks = [],
} ) => {
	const [ activeTask, setActiveTask ] = useState( defaultActiveTask );
	const [ completedTasks, setCompletedTasks ] = useState(
		defaultCompletedTasks
	);

	const contextValue = useMemo(
		() => ( {
			activeTask,
			setActiveTask,
			completedTasks,
			setCompletedTasks,
		} ),
		[ activeTask, setActiveTask, completedTasks, completedTasks ]
	);

	return (
		<SetupTasksControllerContext.Provider value={ contextValue }>
			{ children }
		</SetupTasksControllerContext.Provider>
	);
};

export default SetupTasksController;
