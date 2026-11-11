/**
 * Internal dependencies
 */
import { observeElementVisibility } from '../element-visibility';

describe( 'observeElementVisibility', () => {
	const originalIO = window.IntersectionObserver;
	let callback;
	let observer;

	beforeEach( () => {
		observer = { observe: jest.fn(), disconnect: jest.fn() };
		window.IntersectionObserver = jest.fn( ( cb ) => {
			callback = cb;
			return observer;
		} );
	} );

	afterEach( () => {
		window.IntersectionObserver = originalIO;
	} );

	it( 'reports every visibility change without disconnecting', () => {
		const onVisibilityChange = jest.fn();
		const element = document.createElement( 'div' );
		observeElementVisibility( element, onVisibilityChange );

		expect( observer.observe ).toHaveBeenCalledWith( element );

		callback( [ { isIntersecting: false, intersectionRatio: 0 } ] );
		callback( [ { isIntersecting: true, intersectionRatio: 0.5 } ] );
		callback( [ { isIntersecting: false, intersectionRatio: 0 } ] );
		callback( [ { isIntersecting: true, intersectionRatio: 1 } ] );

		expect( onVisibilityChange.mock.calls ).toEqual( [
			[ false ],
			[ true ],
			[ false ],
			[ true ],
		] );
		expect( observer.disconnect ).not.toHaveBeenCalled();
	} );

	it( 'treats an element below the threshold as hidden', () => {
		const onVisibilityChange = jest.fn();
		observeElementVisibility(
			document.createElement( 'div' ),
			onVisibilityChange
		);

		callback( [ { isIntersecting: true, intersectionRatio: 0.05 } ] );

		expect( onVisibilityChange ).toHaveBeenCalledWith( false );
		expect( window.IntersectionObserver ).toHaveBeenCalledWith(
			expect.any( Function ),
			{ threshold: 0.1 }
		);
	} );

	it( 'reports the most recent entry when several are queued', () => {
		const onVisibilityChange = jest.fn();
		observeElementVisibility(
			document.createElement( 'div' ),
			onVisibilityChange
		);

		callback( [
			{ isIntersecting: true, intersectionRatio: 1 },
			{ isIntersecting: false, intersectionRatio: 0 },
		] );

		expect( onVisibilityChange.mock.calls ).toEqual( [ [ false ] ] );
	} );

	it( 'returns a function that disconnects the observer', () => {
		const disconnect = observeElementVisibility(
			document.createElement( 'div' ),
			jest.fn()
		);
		disconnect();
		expect( observer.disconnect ).toHaveBeenCalled();
	} );

	it( 'treats the element as visible without IntersectionObserver', () => {
		window.IntersectionObserver = undefined;
		const onVisibilityChange = jest.fn();
		observeElementVisibility(
			document.createElement( 'div' ),
			onVisibilityChange
		);
		expect( onVisibilityChange.mock.calls ).toEqual( [ [ true ] ] );
	} );
} );
