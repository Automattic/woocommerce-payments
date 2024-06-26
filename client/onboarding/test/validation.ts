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
	it( 'uses a generic string for a non existing error', () => {
		const { result } = renderHook(
			() => useValidation( 'annual_revenue' ),
			{
				wrapper: OnboardingContextProvider,
			}
		);

		act( () => result.current.validate() );

		expect( result.current.error() ).toEqual( 'Please provide a response' );
	} );
} );
