/**
 * Unit tests for useViewport hook
 */

/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { useViewport } from '../use-viewport';

// Mock the viewport utilities
jest.mock( '../../utils/viewport', () => ( {
	getViewportSize: jest.fn(),
	isVerySmallMobile: jest.fn(),
	isMobile: jest.fn(),
	isTablet: jest.fn(),
	isDesktop: jest.fn(),
} ) );

import {
	getViewportSize,
	isVerySmallMobile,
	isMobile,
	isTablet,
	isDesktop,
} from '../../utils/viewport';

const mockGetViewportSize = getViewportSize as jest.MockedFunction<
	typeof getViewportSize
>;
const mockIsVerySmallMobile = isVerySmallMobile as jest.MockedFunction<
	typeof isVerySmallMobile
>;
const mockIsMobile = isMobile as jest.MockedFunction< typeof isMobile >;
const mockIsTablet = isTablet as jest.MockedFunction< typeof isTablet >;
const mockIsDesktop = isDesktop as jest.MockedFunction< typeof isDesktop >;

describe( 'useViewport', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// Default mock values
		mockGetViewportSize.mockReturnValue( { width: 1024, height: 768 } );
		mockIsVerySmallMobile.mockReturnValue( false );
		mockIsMobile.mockReturnValue( false );
		mockIsTablet.mockReturnValue( false );
		mockIsDesktop.mockReturnValue( true );
	} );

	it( 'should return viewport information', () => {
		const { result } = renderHook( () => useViewport() );

		expect( result.current ).toEqual( {
			viewportSize: { width: 1024, height: 768 },
			isVerySmallMobile: false,
			isMobile: false,
			isTablet: false,
			isDesktop: true,
		} );
	} );

	it( 'should update when window is resized', () => {
		const { result } = renderHook( () => useViewport() );

		// Initial state
		expect( result.current.viewportSize ).toEqual( {
			width: 1024,
			height: 768,
		} );

		// Simulate window resize
		act( () => {
			mockGetViewportSize.mockReturnValue( { width: 800, height: 600 } );
			mockIsVerySmallMobile.mockReturnValue( false );
			mockIsMobile.mockReturnValue( false );
			mockIsTablet.mockReturnValue( true );
			mockIsDesktop.mockReturnValue( false );

			// Trigger resize event
			window.dispatchEvent( new Event( 'resize' ) );
		} );

		// Wait for the debounce to complete
		expect( result.current ).toEqual( {
			viewportSize: { width: 800, height: 600 },
			isVerySmallMobile: false,
			isMobile: false,
			isTablet: true,
			isDesktop: false,
		} );
	} );

	it( 'should handle very small mobile screens', () => {
		mockGetViewportSize.mockReturnValue( { width: 300, height: 600 } );
		mockIsVerySmallMobile.mockReturnValue( true );
		mockIsMobile.mockReturnValue( true );
		mockIsTablet.mockReturnValue( false );
		mockIsDesktop.mockReturnValue( false );

		const { result } = renderHook( () => useViewport() );

		expect( result.current ).toEqual( {
			viewportSize: { width: 300, height: 600 },
			isVerySmallMobile: true,
			isMobile: true,
			isTablet: false,
			isDesktop: false,
		} );
	} );

	it( 'should call viewport functions on each render', () => {
		renderHook( () => useViewport() );

		expect( mockGetViewportSize ).toHaveBeenCalled();
		expect( mockIsVerySmallMobile ).toHaveBeenCalled();
		expect( mockIsMobile ).toHaveBeenCalled();
		expect( mockIsTablet ).toHaveBeenCalled();
		expect( mockIsDesktop ).toHaveBeenCalled();
	} );

	it( 'should add and remove resize event listener', () => {
		const addEventListenerSpy = jest.spyOn( window, 'addEventListener' );
		const removeEventListenerSpy = jest.spyOn(
			window,
			'removeEventListener'
		);

		const { unmount } = renderHook( () => useViewport() );

		expect( addEventListenerSpy ).toHaveBeenCalledWith(
			'resize',
			expect.any( Function )
		);

		unmount();

		expect( removeEventListenerSpy ).toHaveBeenCalledWith(
			'resize',
			expect.any( Function )
		);

		addEventListenerSpy.mockRestore();
		removeEventListenerSpy.mockRestore();
	} );
} );
