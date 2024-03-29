/**
 * Internal dependencies
 */
import { showAuthenticationModalIfRequired } from '../3ds-flow-handling';

describe( 'showAuthenticationModalIfRequired', () => {
	it( 'Should stop processing when no confirmation is needed', () => {
		const replaceStateSpy = jest.spyOn( history, 'replaceState' );
		const apiMock = {
			confirmIntent: jest.fn( () => true ),
		};

		showAuthenticationModalIfRequired( apiMock );

		expect( apiMock.confirmIntent ).toHaveBeenCalled();
		expect( replaceStateSpy ).not.toHaveBeenCalled();
	} );

	it( 'Should cleanup the URL when confirmation is needed', async () => {
		const cleanupURLSpy = jest.spyOn( history, 'replaceState' );
		const mockedRequest = Promise.resolve( 'https://example.com/checkout' );

		const apiMock = {
			confirmIntent: jest.fn( () => mockedRequest ),
		};

		showAuthenticationModalIfRequired( apiMock );

		expect( apiMock.confirmIntent ).toHaveBeenCalled();
		expect( cleanupURLSpy ).toHaveBeenCalled();
	} );
} );
