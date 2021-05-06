/**
 * External dependencies
 */
import React, { useMemo, useContext } from 'react';

import WizardContext from '../parent/context';
import TaskContext from './context';

const TaskContextProvider = ( { children, id = '' } ) => {
	const {
		activeTask,
		completedTasks,
		setActiveTask,
		setCompletedTasks,
	} = useContext( WizardContext );

	const contextValue = useMemo(
		() => ( {
			isActive: id === activeTask,
			setActive: () => setActiveTask( id ),
			setCompleted: ( payload = true, nextTask = '' ) => {
				setCompletedTasks( ( tasks ) => ( {
					...tasks,
					[ id ]: payload,
				} ) );

				if ( nextTask ) {
					setActiveTask( nextTask );
				}
			},
			isCompleted: Boolean( completedTasks[ id ] ),
		} ),
		[ setActiveTask, setCompletedTasks, activeTask, completedTasks, id ]
	);

	return (
		<TaskContext.Provider value={ contextValue }>
			{ children }
		</TaskContext.Provider>
	);
};

export default TaskContextProvider;
