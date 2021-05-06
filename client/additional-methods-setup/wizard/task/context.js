/**
 * External dependencies
 */
import { createContext } from 'react';

const TaskContext = createContext( {
	isActive: false,
	setActive: () => null,
	isCompleted: false,
	setCompleted: () => null,
} );

export default TaskContext;
