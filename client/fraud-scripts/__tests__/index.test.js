/**
 * Internal dependencies
 */
import enqueueFraudScripts from '..';

const V3_URL = 'https://js.stripe.com/v3/';
const DAHLIA_URL = 'https://js.stripe.com/dahlia/stripe.js';
const FRAUD_SERVICES = { stripe: {} };

const addScriptTag = ( src ) => {
	const script = document.createElement( 'script' );
	script.src = src;
	document.head.appendChild( script );
};

const getStripeJsSources = () =>
	Array.from(
		document.querySelectorAll( 'script[src^="https://js.stripe.com/"]' )
	).map( ( script ) => script.src );

describe( 'Stripe fraud script', () => {
	let originalWcpaySettings;

	beforeEach( () => {
		originalWcpaySettings = global.wcpaySettings;
	} );

	afterEach( () => {
		global.wcpaySettings = originalWcpaySettings;
		delete global.wcpay_upe_config;
		document.head.innerHTML = '';
		document.body.innerHTML = '';
	} );

	it( 'adds the admin Stripe.js URL when the page has none', () => {
		global.wcpaySettings = { stripeJsUrl: DAHLIA_URL };

		enqueueFraudScripts( FRAUD_SERVICES );

		expect( getStripeJsSources() ).toEqual( [
			'https://js.stripe.com/dahlia/stripe.js',
		] );
	} );

	it( 'adds the checkout Stripe.js URL when the page has none', () => {
		global.wcpay_upe_config = { stripeJsUrl: DAHLIA_URL };

		enqueueFraudScripts( FRAUD_SERVICES );

		expect( getStripeJsSources() ).toEqual( [
			'https://js.stripe.com/dahlia/stripe.js',
		] );
	} );

	it( 'falls back to v3 without a configured URL', () => {
		global.wcpaySettings = {};

		enqueueFraudScripts( FRAUD_SERVICES );

		expect( getStripeJsSources() ).toEqual( [
			'https://js.stripe.com/v3/',
		] );
	} );

	it.each( [
		[ 'v3', `${ V3_URL }?ver=3.0`, DAHLIA_URL ],
		[ 'Dahlia', DAHLIA_URL, V3_URL ],
	] )(
		'does not add another tag when a %s tag is on the page',
		( _, existingSrc, configuredSrc ) => {
			global.wcpaySettings = { stripeJsUrl: configuredSrc };
			addScriptTag( existingSrc );

			enqueueFraudScripts( FRAUD_SERVICES );

			expect( getStripeJsSources() ).toHaveLength( 1 );
		}
	);
} );
