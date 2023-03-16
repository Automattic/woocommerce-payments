/**
 * External dependencies
 */
import React, { createContext, useContext, useState } from 'react';

/**
 * Internal dependencies
 */
import { OnboardingFields } from './types';

const useContextValue = () => {
	const [ data, setData ] = useState( {} as OnboardingFields );
	const [ errors, setErrors ] = useState( {} as OnboardingFields );

	return {
		data,
		setData: ( value: Record< string, string | undefined > ) =>
			setData( ( prev ) => ( { ...prev, ...value } ) ),
		errors,
		setErrors: ( value: Record< string, string | undefined > ) =>
			setErrors( ( prev ) => ( { ...prev, ...value } ) ),
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
