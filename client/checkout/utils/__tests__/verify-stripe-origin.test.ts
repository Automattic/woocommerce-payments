/**
 * Internal dependencies
 */
import {
	verifyStripeJsOrigin,
	assertStripeJsOrigin,
} from '../verify-stripe-origin';

const addScript = ( attrs: Record< string, string > ): void => {
	const script = document.createElement( 'script' );
	Object.entries( attrs ).forEach( ( [ key, value ] ) =>
		script.setAttribute( key, value )
	);
	document.head.appendChild( script );
};

describe( 'verifyStripeJsOrigin', () => {
	afterEach( () => {
		// Remove scripts and any non-script element planted with the handle id.
		document.head
			.querySelectorAll( 'script, #stripe-js' )
			.forEach( ( el ) => el.remove() );
	} );

	it( 'accepts the canonical WordPress handle tag', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com/v3/?ver=3.0',
		} );

		expect( verifyStripeJsOrigin() ).toEqual( {
			ok: true,
			detectedSrc: 'https://js.stripe.com/v3/?ver=3.0',
			detectedOrigin: 'https://js.stripe.com',
		} );
	} );

	it.each( [
		'https://js.stripe.com/v3/',
		'https://js.stripe.com/v3/stripe.js',
		'https://js.stripe.com/basil/stripe.js',
	] )( 'accepts a legitimate js.stripe.com path: %s', ( src ) => {
		addScript( { src } );

		expect( verifyStripeJsOrigin().ok ).toBe( true );
	} );

	it( 'rejects a look-alike skimmer origin on the repointed handle', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );

		const result = verifyStripeJsOrigin();

		expect( result.ok ).toBe( false );
		expect( result.detectedOrigin ).toBe( 'https://js.evil.example' );
	} );

	it( 'rejects a subdomain-suffix look-alike (js.stripe.com.evil.example)', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com.evil.example/v3/',
		} );

		expect( verifyStripeJsOrigin().ok ).toBe( false );
	} );

	it( 'treats a missing tag as a mismatch', () => {
		expect( verifyStripeJsOrigin() ).toEqual( {
			ok: false,
			detectedSrc: null,
			detectedOrigin: null,
		} );
	} );

	it( 'fails closed when the tag has no src', () => {
		addScript( { id: 'stripe-js' } ); // id present, no src

		expect( verifyStripeJsOrigin() ).toEqual( {
			ok: false,
			detectedSrc: null,
			detectedOrigin: null,
		} );
	} );

	it( 'fails closed when the src cannot be parsed as a URL', () => {
		const fakeDoc = {
			querySelector: () => ( { src: 'https://' } ),
		} as unknown as Document;

		expect( verifyStripeJsOrigin( fakeDoc ) ).toEqual( {
			ok: false,
			detectedSrc: 'https://',
			detectedOrigin: null,
		} );
	} );

	it( 'reads the repointed #stripe-js handle even when a legit tag also exists', () => {
		// The handle tag is enqueued early; the bundled loader appends a real
		// tag later. The attacker-controlled handle must still be the one read.
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );
		addScript( { src: 'https://js.stripe.com/v3/' } );

		expect( verifyStripeJsOrigin().detectedOrigin ).toBe(
			'https://js.evil.example'
		);
	} );

	it( 'prefers the #stripe-js handle even when a legit tag appears earlier in the DOM', () => {
		// Reverse DOM order: a legitimate tag is inserted before the repointed
		// handle. querySelector on a selector list returns the first match in
		// document order, so the handle must be looked up explicitly to win.
		addScript( { src: 'https://js.stripe.com/v3/' } );
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );

		const result = verifyStripeJsOrigin();

		expect( result.ok ).toBe( false );
		expect( result.detectedOrigin ).toBe( 'https://js.evil.example' );
	} );

	it( 'ignores a non-script element sharing the stripe-js id', () => {
		// A planted <div id="stripe-js"> (inserted first) must not divert the
		// lookup: the tag-qualified `script#stripe-js` selector skips it and
		// still reads the real repointed handle.
		const div = document.createElement( 'div' );
		div.id = 'stripe-js';
		document.head.appendChild( div );
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );

		const result = verifyStripeJsOrigin();

		expect( result.ok ).toBe( false );
		expect( result.detectedOrigin ).toBe( 'https://js.evil.example' );
	} );
} );

describe( 'assertStripeJsOrigin', () => {
	let warn: jest.SpyInstance;

	beforeEach( () => {
		warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {
			// Silence the expected warning.
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		document.head
			.querySelectorAll( 'script' )
			.forEach( ( script ) => script.remove() );
	} );

	it( 'resolves silently when Stripe.js comes from the legitimate origin', () => {
		addScript( { id: 'stripe-js', src: 'https://js.stripe.com/v3/' } );

		expect( () => assertStripeJsOrigin() ).not.toThrow();
		expect( warn ).not.toHaveBeenCalled();
	} );

	it( 'throws and warns when the origin is wrong', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );

		expect( () => assertStripeJsOrigin() ).toThrow(
			/provenance check failed/
		);
		// The attacker URL must not leak into the thrown message (console-only).
		expect( () => assertStripeJsOrigin() ).not.toThrow( /evil/ );
		expect( warn ).toHaveBeenCalledWith(
			expect.stringContaining( 'js.evil.example' )
		);
	} );

	it( 'throws with a clear message when no Stripe.js tag is present', () => {
		expect( () => assertStripeJsOrigin() ).toThrow(
			/provenance check failed/
		);
		expect( warn ).toHaveBeenCalledWith(
			expect.stringContaining( 'no Stripe.js script tag' )
		);
	} );

	it( 'in fail-fast mode, ignores a missing tag (still loading)', () => {
		expect( () =>
			assertStripeJsOrigin( { failFast: true } )
		).not.toThrow();
		expect( warn ).not.toHaveBeenCalled();
	} );

	it( 'in fail-fast mode, still throws on a present, wrong-origin tag', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.evil.example/v3/?ver=3.0',
		} );

		expect( () => assertStripeJsOrigin( { failFast: true } ) ).toThrow(
			/provenance check failed/
		);
	} );
} );
