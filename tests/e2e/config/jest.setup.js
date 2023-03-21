/**
 * External dependencies
 */
import {
	enablePageDialogAccept,
	setBrowserViewport,
} from '@wordpress/e2e-test-utils';

import { addConsoleSuppression } from '@woocommerce/e2e-environment';

const ERROR_MESSAGES_TO_IGNORE = [
	'violates the following Content Security Policy directive',
	'You may test your Stripe.js integration over HTTP.',
	'is deprecated',
	'Unrecognized feature:',
	'This Element will be mounted to a DOM element that contains child nodes',
	'Each dictionary in the list',
	'Missing data from PHP (wpNotesArgs).',
	'We were rate-limited from checking if your requested Payment Request options are allowed. Please test again before going live.',
	"Unrecognized Content-Security-Policy directive 'require-trusted-types-for'.",
	'Failed to load resource: the server responded with a status of 400 ()',
	"WebSocket connection to 'wss://public-api.wordpress.com/pinghub/wpcom/me/newest-note-data' failed",
	'Failed to load resource: the server responded with a status of 500 ()',
	'Scripts that have a dependency on',
	'was preloaded using link preload but not used within a few seconds',
	'No UI will be shown. CanMakePayment and hasEnrolledInstrument',
];

ERROR_MESSAGES_TO_IGNORE.forEach( ( errorMessage ) => {
	addConsoleSuppression( errorMessage, false );
} );

/**
 * Array of page event tuples of [ eventName, handler ].
 *
 * @type {Array}
 */
const pageEvents = [];

async function setupBrowser() {
	await setBrowserViewport( 'large' );
}

/**
 * Adds a few event listeners to the page for debugging purposes.
 */
function addPageDebugEvents() {
	page.on( 'pageerror', ( error ) => {
		// eslint-disable-next-line no-console
		console.log( 'pageerror: ' + error.message );
	} );

	page.on( 'response', ( response ) => {
		if ( 200 !== response.status() && 204 !== response.status() ) {
			// eslint-disable-next-line no-console
			console.log( 'response: ' + response.status(), response.url() );
		}
	} );

	page.on( 'requestfailed', ( request ) => {
		// eslint-disable-next-line no-console
		console.log(
			'requestfailed: ' + request.failure().errorText,
			request.url()
		);
	} );
}

/**
 * Adds a special cookie during the session to avoid the support session detection page.
 * This is temporarily displayed when navigating to the login page while Jetpack SSO and protect modules are disabled.
 * Relevant for Atomic sites only.
 */
async function addSupportSessionDetectedCookie() {
	const domain = new URL( process.env.WP_BASE_URL ).hostname;
	await page.setCookie( {
		value: 'true',
		name: '_wpcomsh_support_session_detected',
		domain: domain,
	} );
}

/**
 * Adds an event listener to the page to handle additions of page event
 * handlers, to assure that they are removed at test teardown.
 */
function capturePageEventsForTearDown() {
	page.on( 'newListener', ( eventName, listener ) => {
		pageEvents.push( [ eventName, listener ] );
	} );
}

/**
 * Removes all bound page event handlers.
 */
function removePageEvents() {
	pageEvents.forEach( ( [ eventName, handler ] ) => {
		page.removeListener( eventName, handler );
	} );
}

function setTestTimeouts() {
	const TIMEOUT = 100000;
	// Increase default value to avoid test failing due to timeouts.
	page.setDefaultTimeout( TIMEOUT );
	// running the login flow takes more than the default timeout of 5 seconds,
	// so we need to increase it to run the login in the beforeAll hook
	jest.setTimeout( TIMEOUT );
}

// Before every test suite run, delete all content created by the test. This ensures
// other posts/comments/etc. aren't dirtying tests and tests don't depend on
// each other's side-effects.
beforeAll( async () => {
	if ( process.env.E2E_MORE_DEBUG ) {
		addPageDebugEvents();
	}

	if ( ! process.env.WP_BASE_URL.includes( 'localhost' ) ) {
		await addSupportSessionDetectedCookie();
	}

	capturePageEventsForTearDown();
	enablePageDialogAccept();
	setTestTimeouts();
	await setupBrowser();
} );

afterEach( async () => {
	await setupBrowser();
} );

afterAll( () => {
	removePageEvents();
} );
