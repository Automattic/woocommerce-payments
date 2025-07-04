/**
 * External dependencies
 */
import React, { useContext, useState } from 'react';

/**
 * Internal dependencies
 */
import { StepperContext } from './stepper-context';
import {
	StepperContextValue,
	UseContextValueParams,
	StepperProps,
} from './stepper-types';

const useContextValue = ( {
	steps,
	initialStep,
	onStepChange,
	onComplete,
	onExit,
}: UseContextValueParams ): StepperContextValue => {
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

function childrenToSteps( children: StepperProps[ 'children' ] ) {
	return children.reduce(
		( acc: Record< string, React.ReactElement >, child, index ) => {
			if ( React.isValidElement( child ) ) {
				acc[ child.props.name ?? index ] = child;
			}
			return acc;
		},
		{}
	);
}

export const Stepper: React.FC< React.PropsWithChildren< StepperProps > > = ( {
	children,
	...rest
} ) => {
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

export const useStepperContext = (): StepperContextValue => {
	const context = useContext( StepperContext );
	if ( ! context ) {
		throw new Error( 'useStepperContext() must be used within <Stepper>' );
	}
	return context;
};
