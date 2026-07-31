/**
 * External dependencies
 */
import React, { useCallback, useMemo, useState } from 'react';
import { isNil, omitBy } from 'lodash';

/**
 * Internal dependencies
 */
import { OnboardingContext } from './context';
import { OnboardingContextValue, OnboardingFields } from './types';

const useContextValue = (
	initialState = {} as OnboardingFields
): OnboardingContextValue => {
	const [ data, setData ] = useState( initialState );
	const [ errors, setErrors ] = useState( {} as OnboardingFields );
	const [ touched, setTouched ] = useState< Record< string, boolean > >( {} );

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

export const OnboardingContextProvider: React.FC<
	React.PropsWithChildren< {
		initialData?: OnboardingFields;
	} >
> = ( { children, initialData } ) => {
	const value = useContextValue( initialData );

	return (
		<OnboardingContext.Provider value={ value }>
			{ children }
		</OnboardingContext.Provider>
	);
};
