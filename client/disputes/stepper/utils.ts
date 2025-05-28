/**
 * External dependencies
 */
import { createContext, useContext } from 'react';

/**
 * Internal dependencies
 */

/**
 * WordPress dependencies
 */

// The context value type is imported from stepper.tsx, so we need to define a type placeholder here.
// We'll update Stepper.tsx to export the type for use here.
export type ContextValue = {
	currentStep: string;
	progress: number;
	nextStep: () => void;
	prevStep: () => void;
	exit: () => void;
};

export const StepperContext = createContext< ContextValue | null >( null );

export const useStepperContext = (): ContextValue => {
	const context = useContext( StepperContext );
	if ( ! context ) {
		throw new Error( 'useStepperContext() must be used within <Stepper>' );
	}
	return context;
};
