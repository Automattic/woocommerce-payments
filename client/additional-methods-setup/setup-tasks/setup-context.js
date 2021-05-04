/**
 * External dependencies
 */
import { createContext, useContext } from 'react';

export const SetupTasksControllerContext = createContext( {
	activeTask: '',
	setActiveTask: () => null,
	completedTasks: [],
	setCompletedTasks: () => null,
} );

export const useSetupTasksControllerContext = () =>
	useContext( SetupTasksControllerContext );
