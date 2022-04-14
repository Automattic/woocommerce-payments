/**
 * External dependencies
 */
import { createContext } from 'react';

const WizardTaskContext = createContext( {
	isActive: false,
	setActive: () => null,
	isCompleted: false,
	setCompleted: () => null,
	taskId: '',
} as {
	isActive: boolean;
	setActive: ( payload: string ) => void;
	isCompleted: boolean;
	setCompleted: ( payload: boolean | any, nextTask?: string ) => void;
	taskId: string;
} );

export default WizardTaskContext;
