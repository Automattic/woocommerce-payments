// Types and interfaces for the Stepper component and context

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

export type StepperContextValue = {
	currentStep: string;
	progress: number;
	nextStep: () => void;
	prevStep: () => void;
	exit: () => void;
};

export interface UseContextValueParams {
	steps: Record< string, React.ReactElement >;
	initialStep?: string;
	onStepChange?: ( step: string ) => void;
	onComplete?: () => void;
	onExit?: () => void;
}

export interface StepperProps {
	children: React.ReactElement< { name: string } >[];
	initialStep?: string;
	onStepChange?: ( step: string ) => void;
	onComplete?: () => void;
	onExit?: () => void;
}
