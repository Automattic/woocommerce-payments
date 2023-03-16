/**
 * External dependencies
 */
import { useEffect } from 'react';

/**
 * Internal dependencies
 */
import strings from './strings';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';

const isValid = ( name: keyof OnboardingFields, value?: string ): boolean => {
	if ( ! value ) return false;
	if ( name === 'email' ) return value.includes( '@' );
	return true;
};

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
export const useValidation = ( name: keyof OnboardingFields ) => {
	const { errors, setErrors, touched, setTouched } = useOnboardingContext();

	const validate = ( value?: string ) => {
		if ( ! touched[ name ] ) setTouched( { [ name ]: true } );
		const error = isValid( name, value )
			? undefined
			: ( strings.errors as Record< string, string > )[ name ] ||
			  strings.errors.generic;
		setErrors( { [ name ]: error } );
	};

	useEffect( () => {
		validate();
		setTouched( { [ name ]: false } );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return {
		validate,
		error: () => ( touched[ name ] ? errors[ name ] : undefined ),
	};
};
