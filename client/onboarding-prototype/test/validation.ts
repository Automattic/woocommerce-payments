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
	it( 'sets email error state for an invalid value', () => {
		const { result } = renderHook( () => useValidation( 'email' ), {
			wrapper: OnboardingContextProvider,
		} );

		act( () => result.current.validate( 'invalid' ) );

		expect( result.current.error() ).toEqual(
			'Please provide a valid email'
		);
	} );

	it( 'sets email error state to undefined for a valid value', () => {
		const { result } = renderHook( () => useValidation( 'email' ), {
			wrapper: OnboardingContextProvider,
		} );

		act( () => result.current.validate( 'valid@email.com' ) );

		expect( result.current.error() ).toBeUndefined();
	} );

	it( 'uses a generic string for a non existing error', () => {
		const { result } = renderHook( () => useValidation( 'country' ), {
			wrapper: OnboardingContextProvider,
		} );

		act( () => result.current.validate() );

		expect( result.current.error() ).toEqual( 'Please provide a response' );
	} );
} );
