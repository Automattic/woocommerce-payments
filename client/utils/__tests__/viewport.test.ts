/**
 * Unit tests for viewport utilities
 */

/**
 * Internal dependencies
 */
import {
	getViewportSize,
	isViewportWidthLessThan,
	isViewportWidthGreaterThanOrEqualTo,
	getCurrentBreakpoint,
	isBreakpoint,
	isMobile,
	isTablet,
	isDesktop,
	isVerySmallMobile,
	BREAKPOINTS,
} from '../viewport';

// Mock window object
const mockWindow = {
	innerWidth: 1024,
	innerHeight: 768,
};

// Save original window
const originalWindow = global.window;

describe( 'Viewport Utilities', () => {
	beforeEach( () => {
		// Mock window object
		Object.defineProperty( global, 'window', {
			value: mockWindow,
			writable: true,
		} );
	} );

	afterEach( () => {
		// Restore original window
		Object.defineProperty( global, 'window', {
			value: originalWindow,
			writable: true,
		} );
	} );

	describe( 'getViewportSize', () => {
		it( 'should return current viewport size', () => {
			const size = getViewportSize();
			expect( size ).toEqual( {
				width: 1024,
				height: 768,
			} );
		} );
	} );

	describe( 'isViewportWidthLessThan', () => {
		it( 'should return true when width is less than breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 300,
				writable: true,
			} );
			expect( isViewportWidthLessThan( 400 ) ).toBe( true );
		} );

		it( 'should return false when width is greater than or equal to breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 500,
				writable: true,
			} );
			expect( isViewportWidthLessThan( 400 ) ).toBe( false );
		} );
	} );

	describe( 'isViewportWidthGreaterThanOrEqualTo', () => {
		it( 'should return true when width is greater than or equal to breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 500,
				writable: true,
			} );
			expect( isViewportWidthGreaterThanOrEqualTo( 400 ) ).toBe( true );
		} );

		it( 'should return false when width is less than breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 300,
				writable: true,
			} );
			expect( isViewportWidthGreaterThanOrEqualTo( 400 ) ).toBe( false );
		} );
	} );

	describe( 'getCurrentBreakpoint', () => {
		it( 'should return correct breakpoint for different screen sizes', () => {
			// Test mobile
			Object.defineProperty( global.window, 'innerWidth', {
				value: 300,
				writable: true,
			} );
			expect( getCurrentBreakpoint() ).toBe( 'mobile' );

			// Test tablet (660px is the tablet breakpoint)
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( getCurrentBreakpoint() ).toBe( 'tablet' );

			// Test desktop (800px is the desktop breakpoint)
			Object.defineProperty( global.window, 'innerWidth', {
				value: 900,
				writable: true,
			} );
			expect( getCurrentBreakpoint() ).toBe( 'desktop' );
		} );
	} );

	describe( 'isBreakpoint', () => {
		it( 'should return true for current breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( isBreakpoint( 'tablet' ) ).toBe( true );
		} );

		it( 'should return false for different breakpoint', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( isBreakpoint( 'desktop' ) ).toBe( false );
		} );
	} );

	describe( 'isMobile', () => {
		it( 'should return true for mobile screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 400,
				writable: true,
			} );
			expect( isMobile() ).toBe( true );
		} );

		it( 'should return false for larger screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( isMobile() ).toBe( false );
		} );
	} );

	describe( 'isTablet', () => {
		it( 'should return true for tablet screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( isTablet() ).toBe( true );
		} );

		it( 'should return false for other screen sizes', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 400,
				writable: true,
			} );
			expect( isTablet() ).toBe( false );

			Object.defineProperty( global.window, 'innerWidth', {
				value: 900,
				writable: true,
			} );
			expect( isTablet() ).toBe( false );
		} );
	} );

	describe( 'isDesktop', () => {
		it( 'should return true for desktop screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 900,
				writable: true,
			} );
			expect( isDesktop() ).toBe( true );
		} );

		it( 'should return false for smaller screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 700,
				writable: true,
			} );
			expect( isDesktop() ).toBe( false );
		} );
	} );

	describe( 'isVerySmallMobile', () => {
		it( 'should return true for very small mobile screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 300,
				writable: true,
			} );
			expect( isVerySmallMobile() ).toBe( true );
		} );

		it( 'should return false for larger screens', () => {
			Object.defineProperty( global.window, 'innerWidth', {
				value: 400,
				writable: true,
			} );
			expect( isVerySmallMobile() ).toBe( false );
		} );
	} );

	describe( 'BREAKPOINTS', () => {
		it( 'should have correct breakpoint definitions', () => {
			expect( BREAKPOINTS ).toEqual( [
				{ name: 'mobile', width: 480 },
				{ name: 'tablet', width: 660 },
				{ name: 'desktop', width: 800 },
				{ name: 'wide', width: 960 },
				{ name: 'huge', width: 1040 },
				{ name: 'massive', width: 1280 },
				{ name: 'colossal', width: 1400 },
			] );
		} );
	} );
} );
