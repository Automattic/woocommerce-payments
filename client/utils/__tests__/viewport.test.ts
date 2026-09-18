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

const originalInnerWidth = window.innerWidth;
const originalInnerHeight = window.innerHeight;

/**
 * Resizes the test window.
 *
 * `window` itself cannot be swapped for a plain object: jsdom marks it
 * unforgeable, so `defineProperty( global, 'window' )` throws. Its
 * `innerWidth`/`innerHeight` accessors are configurable, though, so they can be
 * redefined individually.
 *
 * @param {number} width  Viewport width in pixels.
 * @param {number} height Viewport height in pixels. Left untouched when omitted.
 */
const setViewportSize = ( width: number, height?: number ) => {
	Object.defineProperty( window, 'innerWidth', {
		value: width,
		configurable: true,
		writable: true,
	} );

	if ( undefined !== height ) {
		Object.defineProperty( window, 'innerHeight', {
			value: height,
			configurable: true,
			writable: true,
		} );
	}
};

describe( 'Viewport Utilities', () => {
	beforeEach( () => {
		setViewportSize( 1024, 768 );
	} );

	afterEach( () => {
		setViewportSize( originalInnerWidth, originalInnerHeight );
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
			setViewportSize( 300 );
			expect( isViewportWidthLessThan( 400 ) ).toBe( true );
		} );

		it( 'should return false when width is greater than or equal to breakpoint', () => {
			setViewportSize( 500 );
			expect( isViewportWidthLessThan( 400 ) ).toBe( false );
		} );
	} );

	describe( 'isViewportWidthGreaterThanOrEqualTo', () => {
		it( 'should return true when width is greater than or equal to breakpoint', () => {
			setViewportSize( 500 );
			expect( isViewportWidthGreaterThanOrEqualTo( 400 ) ).toBe( true );
		} );

		it( 'should return false when width is less than breakpoint', () => {
			setViewportSize( 300 );
			expect( isViewportWidthGreaterThanOrEqualTo( 400 ) ).toBe( false );
		} );
	} );

	describe( 'getCurrentBreakpoint', () => {
		it( 'should return correct breakpoint for different screen sizes', () => {
			// Test mobile
			setViewportSize( 300 );
			expect( getCurrentBreakpoint() ).toBe( 'mobile' );

			// Test tablet (660px is the tablet breakpoint)
			setViewportSize( 700 );
			expect( getCurrentBreakpoint() ).toBe( 'tablet' );

			// Test desktop (800px is the desktop breakpoint)
			setViewportSize( 900 );
			expect( getCurrentBreakpoint() ).toBe( 'desktop' );
		} );
	} );

	describe( 'isBreakpoint', () => {
		it( 'should return true for current breakpoint', () => {
			setViewportSize( 700 );
			expect( isBreakpoint( 'tablet' ) ).toBe( true );
		} );

		it( 'should return false for different breakpoint', () => {
			setViewportSize( 700 );
			expect( isBreakpoint( 'desktop' ) ).toBe( false );
		} );
	} );

	describe( 'isMobile', () => {
		it( 'should return true for mobile screens', () => {
			setViewportSize( 400 );
			expect( isMobile() ).toBe( true );
		} );

		it( 'should return false for larger screens', () => {
			setViewportSize( 700 );
			expect( isMobile() ).toBe( false );
		} );
	} );

	describe( 'isTablet', () => {
		it( 'should return true for tablet screens', () => {
			setViewportSize( 700 );
			expect( isTablet() ).toBe( true );
		} );

		it( 'should return false for other screen sizes', () => {
			setViewportSize( 400 );
			expect( isTablet() ).toBe( false );

			setViewportSize( 900 );
			expect( isTablet() ).toBe( false );
		} );
	} );

	describe( 'isDesktop', () => {
		it( 'should return true for desktop screens', () => {
			setViewportSize( 900 );
			expect( isDesktop() ).toBe( true );
		} );

		it( 'should return false for smaller screens', () => {
			setViewportSize( 700 );
			expect( isDesktop() ).toBe( false );
		} );
	} );

	describe( 'isVerySmallMobile', () => {
		it( 'should return true for very small mobile screens', () => {
			setViewportSize( 300 );
			expect( isVerySmallMobile() ).toBe( true );
		} );

		it( 'should return false for larger screens', () => {
			setViewportSize( 400 );
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
