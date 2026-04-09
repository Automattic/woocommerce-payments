/**
 * External dependencies
 */
import React, {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
} from 'react';
import { isNil, omitBy } from 'lodash';

/**
 * Internal dependencies
 */
import { OnboardingFields } from './types';

const useContextValue = ( initialState = {} as OnboardingFields ) => {
	const [ data, setData ] = useState( initialState );
	const [ errors, setErrors ] = useState( {} as OnboardingFields );
	const [ touched, setTouched ] = useState( {} as OnboardingFields );

	const updateData = useCallback(
		( value: Record< string, string | undefined > ) =>
			setData( ( prev ) => ( { ...prev, ...value } ) ),
		[]
	);
	const updateErrors = useCallback(
		( value: Record< string, string | undefined > ) =>
			setErrors( ( prev ) => omitBy( { ...prev, ...value }, isNil ) ),
		[]
	);
	const updateTouched = useCallback(
		( value: Record< string, boolean > ) =>
			setTouched( ( prev ) => ( { ...prev, ...value } ) ),
		[]
	);

	return useMemo(
		() => ( {
			data,
			setData: updateData,
			errors,
			setErrors: updateErrors,
			touched,
			setTouched: updateTouched,
		} ),
		[ data, errors, touched, updateData, updateErrors, updateTouched ]
	);
};

type ContextValue = ReturnType< typeof useContextValue >;

const OnboardingContext = createContext< ContextValue | null >( null );

export const OnboardingContextProvider: React.FC< React.PropsWithChildren< {
	initialData?: OnboardingFields;
} > > = ( { children, initialData } ) => {
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
