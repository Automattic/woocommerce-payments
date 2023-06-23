/**
 * External dependencies
 */
import React, { createContext, useContext, useState } from 'react';
import { initial, isNil, omitBy } from 'lodash';

/**
 * Internal dependencies
 */
import { OnboardingFields, TempData } from './types';

const useContextValue = ( initialState = {} as OnboardingFields ) => {
	const [ data, setData ] = useState( initialState );
	const [ errors, setErrors ] = useState( {} as OnboardingFields );
	const [ touched, setTouched ] = useState( {} as OnboardingFields );
	const [ temp, setTemp ] = useState( {} as TempData );

	return {
		data,
		setData: ( value: Record< string, string | undefined > ) =>
			setData( ( prev ) => ( { ...prev, ...value } ) ),
		errors,
		setErrors: ( value: Record< string, string | undefined > ) =>
			setErrors( ( prev ) => omitBy( { ...prev, ...value }, isNil ) ),
		touched,
		setTouched: ( value: Record< string, boolean > ) =>
			setTouched( ( prev ) => ( { ...prev, ...value } ) ),
		temp,
		setTemp: ( value: Partial< TempData > ) =>
			setTemp( ( prev ) => ( { ...prev, ...value } ) ),
	};
};

type ContextValue = ReturnType< typeof useContextValue >;

const OnboardingContext = createContext< ContextValue | null >( null );

export const OnboardingContextProvider: React.FC< {
	initialData?: OnboardingFields;
} > = ( { children, initialData } ) => {
	return (
		<OnboardingContext.Provider value={ useContextValue( initialData ) }>
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
