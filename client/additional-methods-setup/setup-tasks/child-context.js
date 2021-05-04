/**
 * External dependencies
 */
import { createContext, useContext } from 'react';

export const ChildTaskContext = createContext( {
	isActive: false,
	setActive: () => null,
	isCompleted: false,
	setCompleted: () => null,
} );

export const useChildTaskContext = () => useContext( ChildTaskContext );
