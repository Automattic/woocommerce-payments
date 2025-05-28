/**
 * External dependencies
 */
import React, { useState } from 'react';
import { StepperContext, ContextValue } from './utils';

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */

interface UseContextValueParams {
	steps: Record< string, React.ReactElement >;
	initialStep?: string;
	onStepChange?: ( step: string ) => void;
	onComplete?: () => void;
	onExit?: () => void;
}

const useContextValue = ( {
	steps,
	initialStep,
	onStepChange,
	onComplete,
	onExit,
}: UseContextValueParams ): ContextValue => {
	const keys = Object.keys( steps );
	const [ currentStep, setCurrentStep ] = useState(
		initialStep ?? keys[ 0 ]
	);

	const progress = ( keys.indexOf( currentStep ) + 1 ) / keys.length;

	const nextStep = () => {
		const index = keys.indexOf( currentStep );
		const next = keys[ index + 1 ];

		if ( next ) {
			setCurrentStep( next );
			onStepChange?.( next );
		} else {
			onComplete?.();
		}
	};

	const prevStep = () => {
		const index = keys.indexOf( currentStep );
		const prev = keys[ index - 1 ];

		if ( prev ) {
			setCurrentStep( prev );
			onStepChange?.( prev );
		} else {
			onExit?.();
		}
	};

	const exit = () => onExit?.();

	return {
		currentStep,
		progress,
		nextStep,
		prevStep,
		exit,
	};
};

export interface StepperProps {
	children: React.ReactElement< { name: string } >[];
	initialStep?: string;
	onStepChange?: ( step: string ) => void;
	onComplete?: () => void;
	onExit?: () => void;
}

const childrenToSteps = ( children: StepperProps[ 'children' ] ) => {
	return children.reduce(
		( acc: Record< string, React.ReactElement >, child, index ) => {
			if ( React.isValidElement( child ) ) {
				acc[ child.props.name ?? index ] = child;
			}
			return acc;
		},
		{}
	);
};

export const Stepper: React.FC< StepperProps > = ( { children, ...rest } ) => {
	const steps = childrenToSteps( children );
	const value = useContextValue( {
		steps,
		...rest,
	} );
	const CurrentStep = steps[ value.currentStep ];

	return (
		<StepperContext.Provider value={ value }>
			{ CurrentStep }
		</StepperContext.Provider>
	);
};

export type { ContextValue };
