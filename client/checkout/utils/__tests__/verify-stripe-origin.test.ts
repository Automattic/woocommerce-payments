/**
 * Internal dependencies
 */
import {
	assertStripeJsOrigin,
	isStripeJsSrcPending,
} from '../verify-stripe-origin';

const stripeJsHandleId = 'stripe-js';
const repointedSrc = 'https://js.evil.example/v3/?ver=3.0';

const addScript = ( attrs: Record< string, string > ): void => {
	const script = document.createElement( 'script' );
	Object.entries( attrs ).forEach( ( [ key, value ] ) =>
		script.setAttribute( key, value )
	);
	document.head.appendChild( script );
};

const addNonScriptWithHandleId = (): void => {
	const div = document.createElement( 'div' );
	div.id = stripeJsHandleId;
	document.head.appendChild( div );
};

describe( 'assertStripeJsOrigin', () => {
	let warn: jest.SpyInstance;

	beforeEach( () => {
		warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {
			// Silence the expected warning.
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete window.wcpayStripeJsOriginCheckDisabled;
		document.head
			.querySelectorAll( `script, #${ stripeJsHandleId }` )
			.forEach( ( el ) => el.remove() );
	} );

	it.each( [
		'https://js.stripe.com/v3/?ver=3.0',
		'https://js.stripe.com/dahlia/stripe.js',
	] )( 'passes without a warning for %s', ( src ) => {
		addScript( { id: stripeJsHandleId, src } );

		expect( assertStripeJsOrigin ).not.toThrow();
		expect( warn ).not.toHaveBeenCalled();
	} );

	it( 'throws for a repointed handle and keeps the source out of the message', () => {
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( assertStripeJsOrigin ).toThrow(
			new Error( 'Stripe.js provenance check failed.' )
		);
		expect( warn ).toHaveBeenCalledWith(
			expect.stringContaining( 'https://js.evil.example/v3/?ver=3.0' )
		);
	} );

	it( 'throws for a host that only starts with js.stripe.com', () => {
		addScript( {
			id: stripeJsHandleId,
			src: 'https://js.stripe.com.evil.example/v3/',
		} );

		expect( assertStripeJsOrigin ).toThrow(
			new Error( 'Stripe.js provenance check failed.' )
		);
	} );

	it( 'throws for a repointed handle when a genuine tag is also on the page', () => {
		addScript( { src: 'https://js.stripe.com/v3/' } );
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( assertStripeJsOrigin ).toThrow(
			new Error( 'Stripe.js provenance check failed.' )
		);
	} );

	it( 'reads the script tag when another element shares the handle id', () => {
		addNonScriptWithHandleId();
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( assertStripeJsOrigin ).toThrow(
			new Error( 'Stripe.js provenance check failed.' )
		);
	} );

	// A tag without `src` can sit next to a clone printed in a second tag.
	describe.each( [
		[ 'there is no tag', null ],
		[
			'an optimizer moved the src to a data attribute',
			{ id: stripeJsHandleId, 'data-src': 'https://js.stripe.com/v3/' },
		],
		[
			'the src attribute is empty',
			{
				id: stripeJsHandleId,
				src: '',
				'data-src': 'https://js.stripe.com/v3/',
			},
		],
	] )( 'when %s', ( caseName, attrs ) => {
		beforeEach( () => {
			if ( attrs ) {
				addScript( attrs );
			}
		} );

		it( 'throws and says the tag has no src', () => {
			expect( assertStripeJsOrigin ).toThrow(
				new Error( 'Stripe.js provenance check failed.' )
			);
			expect( warn ).toHaveBeenCalledWith(
				expect.stringContaining( 'is missing or has no src' )
			);
		} );

		it( 'passes when the caller allows no src', () => {
			expect( () =>
				assertStripeJsOrigin( { allowNoSrc: true } )
			).not.toThrow();
			expect( warn ).not.toHaveBeenCalled();
		} );
	} );

	it( 'throws for a repointed handle when the caller allows no src', () => {
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( () => assertStripeJsOrigin( { allowNoSrc: true } ) ).toThrow(
			new Error( 'Stripe.js provenance check failed.' )
		);
	} );

	// A blank value is not empty: a non-breaking space loads a script from the page's origin.
	it.each( [ ' ', '\u00a0', '\u000b', '\u2003' ] )(
		'throws when the src attribute is blank but not empty (%j), even when the caller allows no src',
		( src ) => {
			addScript( { id: stripeJsHandleId, src } );

			expect( () =>
				assertStripeJsOrigin( { allowNoSrc: true } )
			).toThrow( new Error( 'Stripe.js provenance check failed.' ) );
		}
	);

	it( 'passes for a repointed handle when the filter turned the check off', () => {
		window.wcpayStripeJsOriginCheckDisabled = true;
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( assertStripeJsOrigin ).not.toThrow();
		expect( warn ).not.toHaveBeenCalled();
	} );
} );

describe( 'isStripeJsSrcPending', () => {
	afterEach( () => {
		delete window.wcpayStripeJsOriginCheckDisabled;
		document.getElementById( stripeJsHandleId )?.remove();
	} );

	it( 'is pending when the tag has no src', () => {
		addScript( {
			id: stripeJsHandleId,
			'data-src': 'https://js.stripe.com/v3/',
		} );

		expect( isStripeJsSrcPending() ).toBe( true );
	} );

	it( 'is pending when there is no tag', () => {
		expect( isStripeJsSrcPending() ).toBe( true );
	} );

	it( 'is not pending once the tag has a src', () => {
		addScript( { id: stripeJsHandleId, src: repointedSrc } );

		expect( isStripeJsSrcPending() ).toBe( false );
	} );

	it( 'is not pending when the filter turned the check off', () => {
		window.wcpayStripeJsOriginCheckDisabled = true;

		expect( isStripeJsSrcPending() ).toBe( false );
	} );
} );
