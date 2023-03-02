/**
 * External dependencies
 */
import React, { createContext, useContext, useState } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingSteps, OnboardingFields } from './types';

const useContextValue = () => {
	const [ step, setStep ] = useState< OnboardingSteps >( 'personal' );
	const [ data, setData ] = useState( {} as OnboardingFields );
	return {
		step,
		setStep,
		data,
		setData: ( value: Record< string, string | undefined > ) =>
			setData( { ...data, ...value } ),
	};
};

type ContextValue = ReturnType< typeof useContextValue >;

const OnboardingContext = createContext< ContextValue | null >( null );

export const OnboardingContextProvider: React.FC = ( { children } ) => {
	return (
		<OnboardingContext.Provider value={ useContextValue() }>
			{ children }
		</OnboardingContext.Provider>
	);
};

export const useOnboardingContext = (): ContextValue => {
	const context = useContext( OnboardingContext );
	if ( ! context ) {
		throw new Error(
			'useOnboardingContext() must be used within <OnboardingContextProvider>'
		);
	}
	return context;
};
