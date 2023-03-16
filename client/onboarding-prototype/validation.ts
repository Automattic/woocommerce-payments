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
	const { data, errors, setErrors } = useOnboardingContext();

	// eslint-disable-next-line react-hooks/exhaustive-deps
	useEffect( () => setErrors( { [ name ]: '' } ), [] );

	return {
		validate: ( value?: string ) => {
			const error = isValid( name, value )
				? undefined
				: ( strings.errors as Record< string, string > )[ name ] ||
				  strings.errors.generic;
			setErrors( { [ name ]: error } );
		},
		getError: () => errors[ name ],
		isTouched: () => data[ name ] !== undefined,
	};
};
