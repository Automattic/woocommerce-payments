/**
 * External dependencies
 */
import React, { createContext, useContext, useState } from 'react';

/**
 * Internal dependencies
 */

interface UseContextValueParams {
	steps: Record< string, React.ReactElement >;
	onComplete?: () => void;
}

const useContextValue = ( { steps, onComplete }: UseContextValueParams ) => {
	const keys = Object.keys( steps );
	const [ currentStep, setCurrentStep ] = useState( keys[ 0 ] );

	const nextStep = () => {
		const index = keys.indexOf( currentStep );
		const next = keys[ index + 1 ];
		if ( next ) {
			setCurrentStep( next );
		} else {
			onComplete?.();
		}
	};

	return {
		currentStep,
		nextStep,
	};
};

type ContextValue = ReturnType< typeof useContextValue >;

const StepperContext = createContext< ContextValue | null >( null );

interface StepperProps {
	children: React.ReactElement< { name: string } >[];
	onComplete?: () => void;
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

export const Stepper: React.FC< StepperProps > = ( {
	children,
	onComplete,
} ) => {
	const steps = childrenToSteps( children );
	const value = useContextValue( { steps, onComplete } );
	const CurrentStep = steps[ value.currentStep ];

	return (
		<StepperContext.Provider value={ value }>
			{ CurrentStep }
		</StepperContext.Provider>
	);
};

export const useStepperContext = (): ContextValue => {
	const context = useContext( StepperContext );
	if ( ! context ) {
		throw new Error( 'useStepperContext() must be used within <Stepper>' );
	}
	return context;
};
