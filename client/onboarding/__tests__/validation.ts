/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { useValidation } from '../validation';
import { OnboardingContextProvider } from '../context';

describe( 'useValidation', () => {
	it( 'uses correct string for error', () => {
		const { result } = renderHook( () => useValidation( 'country' ), {
			wrapper: OnboardingContextProvider,
		} );

		act( () => result.current.validate() );

		expect( result.current.error() ).toEqual( 'Please provide a country' );
	} );
} );
