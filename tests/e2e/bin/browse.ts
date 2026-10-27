/* eslint-disable no-console, @typescript-eslint/naming-convention -- a CLI whose command names are kebab-case. */
/**
 * Drives a browser on this checkout's dev site (`pnpm run up`), to check a
 * change by hand. The commands below call the E2E helpers, with each argument
 * read as JSON when it is JSON; any other command goes to playwright-cli, in
 * the same browser.
 *
 *   pnpm run browse open
 *   pnpm run browse status
 *   pnpm run browse checkout
 *   pnpm run browse card 3ds2
 *   pnpm run browse pay
 *   pnpm run browse snapshot
 */

/**
 * External dependencies
 */
import { spawnSync } from 'child_process';
import { createHash } from 'crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'fs';
import path from 'path';
import { chromium, Page } from '@playwright/test';
import { parse } from 'dotenv';

/**
 * Internal dependencies
 */
import { config } from '../config/default';
import { isUIUnblocked, wpAdminLogin } from '../utils/helpers';
import * as devtools from '../utils/devtools';
import * as merchant from '../utils/merchant';
import * as merchantNavigation from '../utils/merchant-navigation';
import * as shopper from '../utils/shopper';

type Card = typeof config.cards.basic;
type Helper = ( page: Page, ...args: any[] ) => Promise< unknown >;

const rootDir = path.resolve( __dirname, '../../..' );
const workDir = path.resolve( __dirname, '../artifacts/browse' );
const profileDir = path.join( workDir, 'profile' );
// One browser per checkout, so parallel worktrees never share one.
const session = `woopayments-${ createHash( 'sha1' )
	.update( workDir )
	.digest( 'hex' )
	.slice( 0, 8 ) }`;

const readEnv = () => {
	try {
		return parse( readFileSync( path.join( rootDir, '.env' ) ) );
	} catch {
		return {};
	}
};
const env = { ...readEnv(), ...process.env };
// The same port `pnpm run up` uses.
const baseURL = `http://localhost:${ env.WORDPRESS_PORT || '8082' }`;
const admin = {
	username: env.BROWSE_ADMIN_USER || 'admin',
	password: env.BROWSE_ADMIN_PASSWORD || 'admin',
};

const playwrightCli = ( args: string[] ) =>
	spawnSync( 'playwright-cli', [ `-s=${ session }`, ...args ], {
		stdio: 'inherit',
	} ).status ?? 1;

// Connects to the browser playwright-cli keeps open, through the port Chrome
// picked itself and wrote to its profile.
const withPage = async < T >( callback: ( page: Page ) => Promise< T > ) => {
	let browser;
	try {
		const port = readFileSync(
			path.join( profileDir, 'DevToolsActivePort' ),
			'utf8'
		).split( '\n' )[ 0 ];
		browser = await chromium.connectOverCDP( `http://127.0.0.1:${ port }` );
	} catch {
		throw new Error( 'No browser open. Run `pnpm run browse open`.' );
	}
	const page = browser.contexts()[ 0 ].pages()[ 0 ];
	// A browser we connect to has no base URL, so resolve paths like
	// `/checkout` against the site, as the test config does.
	const goto = page.goto.bind( page );
	page.goto = ( url, options ) =>
		goto( new URL( url, baseURL ).href, options );
	try {
		return await callback( page );
	} finally {
		// Only disconnects: the browser stays open for the next command.
		await browser.close();
	}
};

// REST requests as the logged-in user, through the browser's cookies.
const rest = ( page: Page, method: string, restPath: string, data?: unknown ) =>
	page.evaluate(
		async ( request ) => {
			const nonce = await (
				await fetch( '/wp-admin/admin-ajax.php?action=rest-nonce' )
			).text();
			const response = await fetch( '/wp-json' + request.restPath, {
				method: request.method,
				headers: {
					'X-WP-Nonce': nonce,
					'Content-Type': 'application/json',
				},
				body:
					request.data === undefined
						? undefined
						: JSON.stringify( request.data ),
			} );
			return { status: response.status, body: await response.json() };
		},
		{ method: method.toUpperCase(), restPath, data }
	);

const restOrThrow = async (
	page: Page,
	method: string,
	restPath: string,
	data?: unknown
) => {
	const { status, body } = await rest( page, method, restPath, data );
	if ( status >= 400 ) {
		throw new Error( `${ status } ${ body.code }: ${ body.message }` );
	}
	return body;
};

// Store API cart requests need the nonce the Store API itself hands out.
const storeApi = ( page: Page, method: string, storePath: string, data = {} ) =>
	page.evaluate(
		async ( request ) => {
			const cart = await fetch( '/wp-json/wc/store/v1/cart' );
			const response = await fetch(
				'/wp-json/wc/store/v1' + request.storePath,
				{
					method: request.method,
					headers: {
						Nonce: cart.headers.get( 'Nonce' ) ?? '',
						'Content-Type': 'application/json',
					},
					body:
						request.method === 'GET'
							? undefined
							: JSON.stringify( request.data ),
				}
			);
			const body = await response.json();
			if ( ! response.ok ) {
				throw new Error( `${ body.code }: ${ body.message }` );
			}
			return body;
		},
		{ method, storePath, data }
	);

const getStatus = async ( page: Page ) => {
	const account = await restOrThrow(
		page,
		'GET',
		'/wc/v3/payments/accounts'
	);
	const settings = await restOrThrow(
		page,
		'GET',
		'/wc/v3/payments/settings'
	);
	if ( account === false ) {
		return {
			error:
				'Could not fetch the account from the WooPayments server. Check the dev tools API redirect and that the server is running; the WooPayments log has the request error.',
		};
	}
	return {
		accountId: account.account_id,
		country: account.country,
		status: account.status,
		paymentsEnabled: account.payments_enabled,
		testMode: settings.is_test_mode_enabled,
		devMode: settings.is_dev_mode_enabled,
	};
};

const classicCheckoutPath = '/checkout-classic';

const checkoutType = async ( page: Page ) => {
	if ( await page.locator( '.wc-block-checkout' ).count() ) {
		return 'blocks';
	}
	if ( await page.locator( 'form.woocommerce-checkout' ).count() ) {
		return 'classic';
	}
	throw new Error( 'This page has no checkout. Run `checkout` first.' );
};

// Reads test mode from the checkout's own payment config, which guests can see
// too, so `pay` never places a live charge.
const assertTestMode = async ( page: Page, type: string ) => {
	const testMode = await page.evaluate(
		( classic ) =>
			classic
				? ( window as any ).wcpay_upe_config?.testMode
				: ( window as any ).wc?.wcSettings?.getSetting(
						'woocommerce_payments_data'
				  )?.testMode,
		type === 'classic'
	);
	if ( testMode === undefined ) {
		throw new Error( 'WooPayments is not available on this checkout.' );
	}
	if ( ! testMode ) {
		throw new Error( 'WooPayments is in live mode. Switch to test mode.' );
	}
};

const orderReceived = /order-received\/(\d+)/;

// The 3DS challenge is a frame inside Stripe's lightbox frame.
const challengeFrames = [
	'body > div > iframe[name^="__privateStripeFrame"]',
	'iframe[name="stripe-challenge-frame"]',
] as const;

const challengeButton = ( page: Page, name: 'Complete' | 'Fail' ) =>
	page
		.frameLocator( challengeFrames[ 0 ] )
		.frameLocator( challengeFrames[ 1 ] )
		.getByRole( 'button', { name } );

// Waits for whatever placing the order led to: the order, an error or, when
// `challenge` is set, a 3DS challenge.
const paymentOutcome = async ( page: Page, challenge: boolean ) => {
	const error = page.locator(
		'.wc-block-components-notice-banner.is-error, .woocommerce-error'
	);
	const timeout = 60000;
	await Promise.any( [
		page.waitForURL( orderReceived, { timeout } ),
		error.first().waitFor( { timeout } ),
		...( challenge
			? [ challengeButton( page, 'Complete' ).waitFor( { timeout } ) ]
			: [] ),
	] ).catch( () => {
		throw new Error( 'Nothing happened within 60 seconds.' );
	} );
	const order = page.url().match( orderReceived );
	if ( order ) {
		return `Order #${ order[ 1 ] } received.`;
	}
	if ( await error.count() ) {
		throw new Error( ( await error.first().innerText() ).trim() );
	}
	return '3DS challenge open. Run `confirm-3ds` or `confirm-3ds fail`.';
};

const onOff = ( value: string ) => {
	if ( value !== 'on' && value !== 'off' ) {
		throw new Error( `Expected "on" or "off", got "${ value }".` );
	}
	return value === 'on';
};

const toggle =
	( enable: Helper, disable: Helper ) =>
	async ( page: Page, value: string ) => {
		await ( onOff( value ) ? enable( page ) : disable( page ) );
	};

const devtoolsToggles: Record< string, Helper > = {
	'card-testing': toggle(
		devtools.enableCardTestingProtection,
		devtools.disableCardTestingProtection
	),
	disconnected: toggle(
		devtools.enableActAsDisconnectedFromWCPay,
		devtools.disableActAsDisconnectedFromWCPay
	),
};

const isLoginPage = ( url: URL ) => url.pathname.endsWith( '/wp-login.php' );

const logIn = async (
	page: Page,
	user: { username: string; password: string }
) => {
	await page.context().clearCookies();
	await wpAdminLogin( page, user );
	await page
		.waitForURL( ( url ) => ! isLoginPage( url ) )
		.catch( async () => {
			const error = await page
				.locator( '#login_error' )
				.innerText()
				.catch( () => 'no error shown' );
			const hint =
				user === admin
					? ' Set BROWSE_ADMIN_USER and BROWSE_ADMIN_PASSWORD in .env.'
					: '';
			throw new Error(
				`Could not log in as "${ user.username }": ${ error.trim() }${ hint }`
			);
		} );
};

// The E2E helpers worth calling by hand, by command name.
const commands: Record< string, Helper > = {
	status: getStatus,
	login: ( page, user = 'admin' ) => {
		if ( user !== 'admin' && user !== 'customer' ) {
			throw new Error( 'Log in as "admin" or "customer".' );
		}
		return logIn( page, user === 'admin' ? admin : config.users.customer );
	},
	logout: ( page ) => page.context().clearCookies(),
	// Adds what the shopper commands expect and the dev site lacks. Existing
	// data is left as it is.
	seed: async ( page ) => {
		const created = [];
		const { email } = config.users.customer;
		const customers = await restOrThrow(
			page,
			'GET',
			`/wc/v3/customers?email=${ encodeURIComponent( email ) }`
		);
		if ( ! customers.length ) {
			const { billing, shipping } = config.addresses.customer;
			const address = ( a: typeof billing ) => ( {
				first_name: a.firstname,
				last_name: a.lastname,
				company: a.company,
				address_1: a.addressfirstline,
				address_2: a.addresssecondline,
				city: a.city,
				state: a.state,
				postcode: a.postcode,
				country: a.country_code,
				phone: a.phone,
			} );
			await restOrThrow( page, 'POST', '/wc/v3/customers', {
				email,
				username: config.users.customer.username,
				password: config.users.customer.password,
				first_name: billing.firstname,
				last_name: billing.lastname,
				billing: { ...address( billing ), email },
				shipping: address( shipping ),
			} );
			created.push( `customer user "${ config.users.customer.username }"` );
		}
		const coupons = await restOrThrow(
			page,
			'GET',
			'/wc/v3/coupons?code=free'
		);
		if ( ! coupons.length ) {
			await restOrThrow( page, 'POST', '/wc/v3/coupons', {
				code: 'free',
				amount: '100',
				discount_type: 'percent',
				individual_use: true,
				free_shipping: true,
			} );
			created.push( 'coupon "free"' );
		}
		// The site's checkout page stays as it is, so the classic checkout
		// gets a page of its own.
		const pages = await restOrThrow(
			page,
			'GET',
			`/wp/v2/pages?slug=${ classicCheckoutPath.slice( 1 ) }&status=any`
		);
		if ( ! pages.length ) {
			await restOrThrow( page, 'POST', '/wp/v2/pages', {
				title: 'Checkout (classic)',
				slug: classicCheckoutPath.slice( 1 ),
				status: 'publish',
				content:
					'<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			} );
			created.push( `page "${ classicCheckoutPath }"` );
		}
		return created.length
			? `Created ${ created.join( ', ' ) }.`
			: 'Nothing to create.';
	},
	settings: merchantNavigation.goToWooPaymentsSettings,
	'wc-settings': merchantNavigation.goToWooCommerceSettings,
	order: ( page, id ) => merchantNavigation.goToOrder( page, String( id ) ),
	'payment-details': merchantNavigation.goToPaymentDetails,
	transactions: merchantNavigation.goToTransactions,
	'enable-methods': merchant.enablePaymentMethods,
	'disable-methods': merchant.disablePaymentMethods,
	'capture-later': async ( page, value ) => {
		if ( onOff( value ) ) {
			await merchant.activateCaptureLater( page );
		} else if ( await merchant.isCaptureLaterEnabled( page ) ) {
			// Saving fails when nothing changed.
			await merchant.deactivateCaptureLater( page );
		}
	},
	devtools: ( page, name, value ) => {
		if ( ! devtoolsToggles[ name ] ) {
			throw new Error(
				`Unknown dev tools setting. Use one of: ${ Object.keys(
					devtoolsToggles
				).join( ', ' ) }.`
			);
		}
		return devtoolsToggles[ name ]( page, value );
	},
	// Empties the cart, adds one product (an ID or a slug) and fills in the
	// billing address, as whoever is logged in.
	checkout: async ( page, ...args ) => {
		const classic = args.includes( '--classic' );
		const product =
			args.find( ( arg ) => arg !== '--classic' ) ?? 'beanie';
		const id =
			typeof product === 'number'
				? product
				: (
						await storeApi(
							page,
							'GET',
							`/products?slug=${ encodeURIComponent( product ) }`
						)
				  )[ 0 ]?.id;
		if ( ! id ) {
			throw new Error( `No product "${ product }".` );
		}
		await storeApi( page, 'DELETE', '/cart/items' );
		await storeApi( page, 'POST', '/cart/add-item', { id, quantity: 1 } );
		const billing = config.addresses.customer.billing;
		if ( classic ) {
			await page.goto( classicCheckoutPath );
			if ( ! ( await page.locator( 'form.woocommerce-checkout' ).count() ) ) {
				throw new Error( 'No classic checkout page. Run `seed` first.' );
			}
			await shopper.fillBillingAddress( page, billing );
			// The address change reloads the payment methods.
			await shopper.waitForUiRefresh( page );
			await isUIUnblocked( page );
			return;
		}
		await page.goto( '/checkout' );
		await page
			.getByRole( 'heading', { name: 'Contact information' } )
			.waitFor();
		if ( ( await checkoutType( page ) ) !== 'blocks' ) {
			throw new Error(
				'The checkout page has no checkout block. Use `checkout --classic`.'
			);
		}
		// The helper only opens a saved address if it has rendered already.
		const billingForm = page.getByRole( 'group', {
			name: 'Billing address',
		} );
		await Promise.any( [
			page.getByLabel( 'Edit billing address' ).waitFor(),
			billingForm.getByLabel( 'Country/Region' ).waitFor(),
		] );
		// The helper leaves out the email, which logged-in shoppers already
		// have.
		const email = page.getByLabel( 'Email address' );
		if ( ! ( await email.inputValue() ) ) {
			await email.fill( billing.email );
		}
		await shopper.fillBillingAddressWCB( page, billing );
	},
	card: async ( page, name = 'basic' ) => {
		const cards: Record< string, Card > = config.cards;
		if ( ! cards[ name ] ) {
			throw new Error(
				`Unknown card. Use one of: ${ Object.keys( cards ).join(
					', '
				) }.`
			);
		}
		await ( ( await checkoutType( page ) ) === 'classic'
			? shopper.fillCardDetails( page, cards[ name ] )
			: shopper.fillCardDetailsWCB( page, cards[ name ] ) );
	},
	pay: async ( page ) => {
		const type = await checkoutType( page );
		await assertTestMode( page, type );
		// An error from an earlier attempt stays on the page for a moment, so
		// wait for it to go before looking for a new one.
		const stale = await page
			.locator(
				'.wc-block-components-notice-banner.is-error, .woocommerce-error'
			)
			.elementHandles();
		await ( type === 'classic'
			? shopper.placeOrder( page )
			: shopper.placeOrderWCB( page, false ) );
		await Promise.any( [
			Promise.all(
				stale.map( ( notice ) =>
					notice.waitForElementState( 'hidden', { timeout: 10000 } )
				)
			),
			page.waitForURL( orderReceived, { timeout: 10000 } ),
		] ).catch( () => undefined );
		return paymentOutcome( page, true );
	},
	// A new connection can't reach into the challenge's nested Stripe frames,
	// so the click goes through playwright-cli, which has stayed connected.
	'confirm-3ds': async ( page, result = 'complete' ) => {
		const name = result === 'fail' ? 'Fail' : 'Complete';
		const { status } = spawnSync(
			'playwright-cli',
			[
				`-s=${ session }`,
				'run-code',
				`async page => { await page.frameLocator( ${ JSON.stringify(
					challengeFrames[ 0 ]
				) } ).frameLocator( ${ JSON.stringify(
					challengeFrames[ 1 ]
				) } ).getByRole( 'button', { name: '${ name }' } ).click( { timeout: 10000 } ); }`,
			],
			{ encoding: 'utf8' }
		);
		if ( status !== 0 ) {
			throw new Error( 'No 3DS challenge is open.' );
		}
		return paymentOutcome( page, false );
	},
	api: ( page, method, restPath, data ) =>
		restOrThrow( page, method, restPath, data ),
};

// Chrome's password manager warns about the dev site's leaked test passwords
// in a prompt nobody sees, and that prompt stops later logins in the tab.
const disablePasswordManager = () => {
	const preferencesFile = path.join( profileDir, 'Default', 'Preferences' );
	let preferences: Record< string, any > = {};
	try {
		preferences = JSON.parse( readFileSync( preferencesFile, 'utf8' ) );
	} catch {
		mkdirSync( path.dirname( preferencesFile ), { recursive: true } );
	}
	preferences.credentials_enable_service = false;
	preferences.profile = {
		...preferences.profile,
		password_manager_enabled: false,
		password_manager_leak_detection: false,
	};
	writeFileSync( preferencesFile, JSON.stringify( preferences ) );
};

const open = async ( headless: boolean ) => {
	mkdirSync( workDir, { recursive: true } );
	disablePasswordManager();
	const cliConfig = path.join( workDir, 'cli.config.json' );
	writeFileSync(
		cliConfig,
		JSON.stringify( {
			outputDir: workDir,
			browser: {
				userDataDir: profileDir,
				launchOptions: {
					headless,
					args: [ '--remote-debugging-port=0' ],
				},
			},
		} )
	);
	if ( playwrightCli( [ 'open', `--config=${ cliConfig }` ] ) !== 0 ) {
		throw new Error( 'playwright-cli could not open the browser.' );
	}
	await withPage( ( page ) => logIn( page, admin ) );
	return `Logged in to ${ baseURL } as ${ admin.username }.`;
};

const run = ( command: string, args: unknown[] ) => {
	if ( command === 'open' ) {
		return open( args[ 0 ] === '--headless' );
	}
	return withPage( async ( page ) => {
		const result = await commands[ command ]( page, ...args );
		// A page that needs a login redirects instead of failing.
		if ( command !== 'logout' && isLoginPage( new URL( page.url() ) ) ) {
			throw new Error( 'Not logged in. Run `pnpm run browse login`.' );
		}
		return result;
	} );
};

const [ command, ...args ] = process.argv.slice( 2 );
if ( command === 'open' || Object.hasOwn( commands, command ) ) {
	run(
		command,
		args.map( ( arg ) => {
			try {
				return JSON.parse( arg );
			} catch {
				return arg;
			}
		} )
	)
		.then( ( result ) => {
			if ( typeof result === 'string' ) {
				console.log( result );
			} else if ( result !== undefined ) {
				console.log( JSON.stringify( result, null, '\t' ) );
			}
		} )
		.catch( ( error ) => {
			console.error( error.message );
			process.exitCode = 1;
		} );
} else {
	process.exitCode = playwrightCli( process.argv.slice( 2 ) );
}
