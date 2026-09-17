/**
 * Internal dependencies
 */
import { showAuthenticationModalIfRequired } from '../3ds-flow-handling';
import { redirectTo } from 'wcpay/utils/navigation';

// jsdom marks `window.location` unforgeable and refuses cross-document
// navigation, so the redirect goes through the `redirectTo` helper.
jest.mock( 'wcpay/utils/navigation', () => ( {
	redirectTo: jest.fn(),
} ) );

describe( 'showAuthenticationModalIfRequired', () => {
	beforeEach( () => {
		redirectTo.mockClear();
	} );

	it( 'Should stop processing when no confirmation is needed', () => {
		const replaceStateSpy = jest.spyOn( history, 'replaceState' );
		const apiMock = {
			confirmIntent: jest.fn( () => true ),
		};

		showAuthenticationModalIfRequired( apiMock );

		expect( apiMock.confirmIntent ).toHaveBeenCalled();
		expect( replaceStateSpy ).not.toHaveBeenCalled();
		expect( redirectTo ).not.toHaveBeenCalled();
	} );

	it( 'Should cleanup the URL when confirmation is needed', async () => {
		const cleanupURLSpy = jest.spyOn( history, 'replaceState' );
		const mockedRequest = Promise.resolve( 'https://example.com/checkout' );

		const apiMock = {
			confirmIntent: jest.fn( () => mockedRequest ),
		};

		await showAuthenticationModalIfRequired( apiMock );

		expect( apiMock.confirmIntent ).toHaveBeenCalled();
		expect( cleanupURLSpy ).toHaveBeenCalled();
		expect( redirectTo ).toHaveBeenCalledWith(
			'https://example.com/checkout'
		);
	} );
} );
