/**
 * Internal dependencies
 */
import { verifyStripeJsOrigin, stripeJsOrigin } from '../verify-stripe-origin';

const addScript = ( attrs: Record< string, string > ): void => {
	const script = document.createElement( 'script' );
	Object.entries( attrs ).forEach( ( [ key, value ] ) =>
		script.setAttribute( key, value )
	);
	document.head.appendChild( script );
};

describe( 'verifyStripeJsOrigin', () => {
	afterEach( () => {
		document.head
			.querySelectorAll( 'script' )
			.forEach( ( script ) => script.remove() );
	} );

	it( 'accepts the canonical WordPress handle tag', () => {
		addScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com/v3/?ver=3.0',
		} );

		expect( verifyStripeJsOrigin() ).toEqual( {
			ok: true,
			detectedSrc: 'https://js.stripe.com/v3/?ver=3.0',
			detectedOrigin: stripeJsOrigin,
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
} );
