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

	it( 'calls back once the element intersects, then disconnects', () => {
		const onVisible = jest.fn();
		const element = document.createElement( 'div' );
		observeElementVisibility( element, onVisible );

		expect( observer.observe ).toHaveBeenCalledWith( element );
		callback( [ { isIntersecting: false } ] );
		expect( onVisible ).not.toHaveBeenCalled();

		callback( [ { isIntersecting: true } ] );
		expect( onVisible ).toHaveBeenCalledTimes( 1 );
		expect( observer.disconnect ).toHaveBeenCalled();
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
		const onVisible = jest.fn();
		observeElementVisibility( document.createElement( 'div' ), onVisible );
		expect( onVisible ).toHaveBeenCalledTimes( 1 );
	} );
} );
