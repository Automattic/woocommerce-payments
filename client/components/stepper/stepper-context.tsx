/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */
import { StepperContextValue } from './stepper-types';

export const StepperContext = createContext< StepperContextValue | null >(
	null
);
