/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * Internal dependencies
 */
import { StepperContextValue } from './stepper-types';

export const StepperContext = createContext< StepperContextValue | null >(
	null
);
