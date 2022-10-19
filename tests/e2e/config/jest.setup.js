/**
 * External dependencies
 */
import {
	enablePageDialogAccept,
	setBrowserViewport,
} from '@wordpress/e2e-test-utils';

import { addConsoleSuppression } from '@woocommerce/e2e-environment';

import { configureToMatchImageSnapshot } from 'jest-image-snapshot';

const toMatchImageSnapshot = configureToMatchImageSnapshot( {
	customDiffConfig: {
		threshold: 0.1,
	},
	customSnapshotsDir: './snapshots',
	customDiffDir: './snapshots/diff',
	blur: 1,
	allowSizeMismatch: true,
} );
expect.extend( { toMatchImageSnapshot } );

const ERROR_MESSAGES_TO_IGNORE = [
	'violates the following Content Security Policy directive',
	'You may test your Stripe.js integration over HTTP.',
	'is deprecated',
	'Unrecognized feature:',
	'This Element will be mounted to a DOM element that contains child nodes',
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
