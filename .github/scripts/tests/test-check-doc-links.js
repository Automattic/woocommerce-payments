/**
 * External dependencies
 */
const assert = require( 'node:assert/strict' );
const { execFile, execFileSync } = require( 'node:child_process' );
const { once } = require( 'node:events' );
const {
	mkdirSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	symlinkSync,
	writeFileSync,
} = require( 'node:fs' );
const { createServer } = require( 'node:http' );
const { connect } = require( 'node:net' );
const { tmpdir } = require( 'node:os' );
const { dirname, join, resolve } = require( 'node:path' );
const { after, before, beforeEach, describe, test } = require( 'node:test' );
const { setTimeout: sleep } = require( 'node:timers/promises' );
const { promisify } = require( 'node:util' );

/**
 * Internal dependencies
 */
const {
	discoverLinks,
	fetchPage,
	checkLinks,
	makeReport,
} = require( '../check-doc-links' );

const SCRIPT = resolve( __dirname, '../check-doc-links.js' );
const DOC_URL = 'https://woocommerce.com/document/example/';
const exec = promisify( execFile );

function fixture( context, files ) {
	const root = mkdtempSync( join( tmpdir(), 'wcpay-doc-links-' ) );
	context.after( () => rmSync( root, { recursive: true, force: true } ) );
	execFileSync( 'git', [ 'init', '-q', root ] );
	for ( const [ name, content ] of Object.entries( files ) ) {
		const path = join( root, name );
		mkdirSync( dirname( path ), { recursive: true } );
		writeFileSync( path, content );
	}
	execFileSync( 'git', [ 'add', '.' ], { cwd: root } );
	return root;
}

test( 'scans readme.txt with source locations', ( context ) => {
	const root = fixture( context, {
		'readme.txt': [
			'=== WooPayments ===',
			'[Fees](https://woocommerce.com/document/woopayments/fees/).',
			'[Countries](https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries)',
			'[Product](https://woocommerce.com/payments/)',
		].join( '\n' ),
		'README.md': DOC_URL + '#developer-readme',
		'client/readme.txt': DOC_URL + '#nested-readme',
	} );
	assert.deepEqual( Object.fromEntries( discoverLinks( root ) ), {
		'https://woocommerce.com/document/woopayments/fees/': [
			'readme.txt:2',
		],
		'https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries':
			[ 'readme.txt:3' ],
	} );
} );

test( 'scans tracked source extensions and excludes tests, dependencies, bundles and symlinks', ( context ) => {
	const sources = [
		'woocommerce-payments.php',
		'includes/example.php',
		'src/Example.php',
		'client/example.js',
		'client/example.jsx',
		'client/example.ts',
		'client/example.tsx',
		'includes/multi-currency/client/example.js',
		'templates/example.php',
		'assets/example.js',
	];
	const excluded = [
		'client/__tests__/example.tsx',
		'client/example.test.js',
		'client/example.spec.ts',
		'client/example.min.js',
		'tests/example.php',
		'vendor/example.php',
		'node_modules/example.js',
		'dist/example.js',
		'docs/example.js',
		'lib/packages/example.php',
		'client/README.md',
	];
	const root = fixture(
		context,
		Object.fromEntries(
			[ ...sources, ...excluded ].map( ( name ) => [
				name,
				`// Comment\nconst url = "${ DOC_URL }#section";\n`,
			] )
		)
	);
	symlinkSync(
		join( root, 'client/example.js' ),
		join( root, 'client/symlink.js' )
	);
	execFileSync( 'git', [ 'add', '.' ], { cwd: root } );
	writeFileSync(
		join( root, 'client/untracked.js' ),
		DOC_URL + '#untracked'
	);
	const links = discoverLinks( root );
	assert.deepEqual(
		[ ...links.keys() ],
		[ 'https://woocommerce.com/document/example/#section' ]
	);
	assert.deepEqual(
		links.get( DOC_URL + '#section' ).sort(),
		sources.map( ( name ) => `${ name }:2` ).sort()
	);
} );

test( 'recognizes supported URL forms and exact domain boundaries', ( context ) => {
	const root = fixture( context, {
		'example.php': [
			'<a href="https://www.woocommerce.com/documentation/products/foo/?a=1&amp;b=2#hello%20world" />',
			"'http://docs.woocommerce.com/document/old/#section'",
			'`https://woocommerce.com/docs/example/#section`',
			String.raw`"https:\/\/woocommerce.com\/document\/escaped\/#section"`,
			'// https://woocommerce.com/document/comment/.',
			"'https://woocommerce.com/products/example/'",
			"'https://woocommerce.com/documentary/'",
			"'https://woocommerce.com.evil.example/document/example/'",
		].join( '\n' ),
	} );
	assert.deepEqual(
		[ ...discoverLinks( root ).keys() ].sort(),
		[
			'https://www.woocommerce.com/documentation/products/foo/?a=1&b=2#hello%20world',
			'http://docs.woocommerce.com/document/old/#section',
			'https://woocommerce.com/docs/example/#section',
			'https://woocommerce.com/document/escaped/#section',
			'https://woocommerce.com/document/comment/',
		].sort()
	);
} );

describe( 'HTTP checks with a local server', () => {
	let server, base;
	const requests = new Map();
	let active = 0,
		maxActive = 0;
	const html = `<html><h2 id="present">Title</h2><div ID='hello world'></div>
		<div id="a&amp;b"></div><div id=unquoted></div><a name="legacy"></a>
		<!-- <div id="comment-only"></div> -->
		<script>const fake = '<div id="script-only"></div>';</script></html>`;
	const fetcher = ( url, options = {} ) =>
		fetchPage( url, {
			hosts: new Set( [ '127.0.0.1' ] ),
			retryDelay: 0,
			...options,
		} );
	const check = ( paths ) =>
		checkLinks(
			new Map(
				paths.map( ( path ) => [
					base + path,
					[ 'client/example.tsx:2' ],
				] )
			),
			{ fetcher, log: () => {} }
		);

	before( async () => {
		server = createServer( async ( request, response ) => {
			const path = new URL( request.url, 'http://localhost' ).pathname;
			requests.set( path, ( requests.get( path ) || 0 ) + 1 );
			const redirects = {
				'/redirect': '/ok',
				'/override': '/ok#present',
				'/clear': '/ok#',
				'/chain': '/override',
				'/loop': '/loop',
				'/offsite': 'https://example.com/document/elsewhere/',
				'/credentials': 'http://user:pass@127.0.0.1/ok',
			};
			if ( redirects[ path ] ) {
				response
					.writeHead( 302, { Location: redirects[ path ] } )
					.end();
				return;
			}
			if ( path === '/disconnect' ) {
				request.socket.destroy();
				return;
			}
			if ( path === '/timeout' ) {
				return;
			}
			if ( path.startsWith( '/concurrent/' ) ) {
				active++;
				maxActive = Math.max( active, maxActive );
				await sleep( 20 );
				active--;
			}
			let status = 200;
			if ( path === '/missing' ) {
				status = 404;
			} else if ( path === '/blocked' ) {
				status = 403;
			} else if ( path === '/retry' && requests.get( path ) === 1 ) {
				status = 429;
			} else if ( path === '/unavailable' ) {
				status = 503;
			}
			response.writeHead( status, {
				'Content-Type':
					path === '/json'
						? 'application/json'
						: 'text/html; charset=utf-8',
			} );
			if ( path === '/oversized' ) {
				response.end( 'x'.repeat( 10 * 1024 * 1024 + 1 ) );
			} else {
				response.end( html );
			}
		} );
		// Node's fetch uses CONNECT tunnels even for HTTP proxy destinations.
		server.on( 'connect', ( request, client, head ) => {
			const upstream = connect(
				server.address().port,
				'127.0.0.1',
				() => {
					client.write(
						'HTTP/1.1 200 Connection Established\r\n\r\n'
					);
					upstream.write( head );
					client.pipe( upstream );
					upstream.pipe( client );
				}
			);
			client.on( 'error', () => upstream.destroy() );
			upstream.on( 'error', () => client.destroy() );
		} );
		server.listen( 0, '127.0.0.1' );
		await once( server, 'listening' );
		base = `http://127.0.0.1:${ server.address().port }`;
	} );
	after( async () => {
		server.closeAllConnections();
		await new Promise( ( done ) => server.close( done ) );
	} );
	beforeEach( () => {
		requests.clear();
		active = 0;
		maxActive = 0;
	} );

	test( 'matches exact decoded IDs, ignores named anchors, comments and scripts, and deduplicates pages', async () => {
		const results = await check( [
			'/ok',
			'/ok#present',
			'/ok#hello%20world',
			'/ok#a%26b',
			'/ok#unquoted',
			'/ok#missing',
			'/ok#Present',
			'/ok#legacy',
			'/ok#comment-only',
			'/ok#script-only',
		] );
		assert.equal( [ ...results.values() ].filter( Boolean ).length, 5 );
		for ( const fragment of [
			'present',
			'hello%20world',
			'a%26b',
			'unquoted',
		] ) {
			assert.equal( results.get( base + '/ok#' + fragment ), '' );
		}
		assert.equal( requests.get( '/ok' ), 1 );
	} );

	test( 'redirects preserve, replace and clear fragments', async () => {
		const results = await check( [
			'/redirect#present',
			'/redirect#missing',
			'/override#old',
			'/clear#old',
			'/chain#old',
		] );
		assert.equal( results.get( base + '/redirect#present' ), '' );
		assert.match(
			results.get( base + '/redirect#missing' ),
			/Missing anchor #missing/
		);
		assert.equal( results.get( base + '/override#old' ), '' );
		assert.equal( results.get( base + '/clear#old' ), '' );
		assert.equal( results.get( base + '/chain#old' ), '' );
	} );

	test( 'fails 404s, access blocks, non-HTML, forbidden redirects and redirect loops without retrying', async () => {
		const results = await check( [
			'/missing',
			'/blocked',
			'/json',
			'/offsite',
			'/credentials',
			'/loop',
		] );
		assert.ok( [ ...results.values() ].every( Boolean ) );
		assert.match( results.get( base + '/missing' ), /HTTP 404/ );
		assert.match( results.get( base + '/blocked' ), /HTTP 403/ );
		assert.equal( results.get( base + '/json' ), 'Response is not HTML' );
		assert.match(
			results.get( base + '/offsite' ),
			/not a WooCommerce documentation host/
		);
		assert.match(
			results.get( base + '/credentials' ),
			/not a WooCommerce documentation host/
		);
		assert.equal( results.get( base + '/loop' ), 'Too many redirects' );
		assert.equal( requests.get( '/missing' ), 1 );
		assert.equal( requests.get( '/blocked' ), 1 );
		assert.equal( requests.get( '/offsite' ), 1 );
	} );

	test( 'retries transient statuses and fails when attempts are exhausted', async () => {
		const results = await check( [ '/retry#present', '/unavailable' ] );
		assert.equal( results.get( base + '/retry#present' ), '' );
		assert.match( results.get( base + '/unavailable' ), /HTTP 503/ );
		assert.equal( requests.get( '/retry' ), 2 );
		assert.equal( requests.get( '/unavailable' ), 3 );
	} );

	test( 'retries actual network disconnects', async () => {
		const page = await fetcher( base + '/disconnect' );
		assert.match( page.error, /Request failed/ );
		assert.equal( requests.get( '/disconnect' ), 3 );
	} );

	test( 'enforces request timeouts and the HTML size limit', async () => {
		const timedOut = await fetcher( base + '/timeout', {
			timeout: 50,
			attempts: 1,
		} );
		assert.match( timedOut.error, /Request failed.*timeout/i );
		const oversized = await fetcher( base + '/oversized' );
		assert.equal( oversized.error, 'HTML exceeds the 10 MiB limit' );
		assert.equal( requests.get( '/oversized' ), 1 );
	} );

	test( 'limits concurrent page requests to four', async () => {
		const results = await check(
			Array.from(
				{ length: 9 },
				( _, index ) => `/concurrent/${ index }#present`
			)
		);
		assert.ok( [ ...results.values() ].every( ( error ) => error === '' ) );
		assert.equal( maxActive, 4 );
		assert.equal( requests.size, 9 );
	} );

	test( 'reports malformed fragments without aborting other checks', async () => {
		const results = await check( [ '/ok#%', '/ok#present' ] );
		assert.match( results.get( base + '/ok#%' ), /Invalid URL fragment/ );
		assert.equal( results.get( base + '/ok#present' ), '' );
	} );

	test( 'CLI returns failure or success and writes the report and job summary', async ( context ) => {
		for ( const [ suffix, expectedExit ] of [
			[ '#present', 0 ],
			[ '#missing', 1 ],
			[ null, 1 ],
		] ) {
			const root = fixture( context, {
				'example.php':
					suffix === null
						? '<?php // no URLs'
						: 'http://woocommerce.com/document/example/' + suffix,
			} );
			execFileSync(
				'git',
				[
					'-c',
					'user.name=Test',
					'-c',
					'user.email=test@example.com',
					'-c',
					'commit.gpgsign=false',
					'commit',
					'-qm',
					'Fixture',
				],
				{ cwd: root }
			);
			const reportPath = join( root, 'report.md' );
			const summaryPath = join( root, 'summary.md' );
			writeFileSync( summaryPath, 'Existing summary\n' );
			// Route the CLI's real HTTP requests through this local fixture server.
			const env = {
				...process.env,
				NODE_USE_ENV_PROXY: '1',
				HTTP_PROXY: base,
				http_proxy: base,
				HTTPS_PROXY: base,
				https_proxy: base,
				NO_PROXY: '',
				no_proxy: '',
				GITHUB_STEP_SUMMARY: summaryPath,
			};
			const result = await exec(
				process.execPath,
				[ SCRIPT, '--root', root, '--report', reportPath ],
				{ env, timeout: 5000 }
			).then(
				( output ) => ( { ...output, code: 0 } ),
				( error ) => error
			);
			assert.equal(
				result.code,
				expectedExit,
				result.stderr + result.stdout
			);
			const report = readFileSync( reportPath, 'utf8' );
			assert.equal(
				readFileSync( summaryPath, 'utf8' ),
				'Existing summary\n' + report
			);
			assert.match( report, /unique URLs checked/ );
			if ( suffix === '#missing' ) {
				assert.match( report, /Missing anchor #missing/ );
				assert.match( report, /example.php:1/ );
			} else if ( suffix === null ) {
				assert.match( report, /no documentation URLs were found/ );
			}
		}
	} );
} );

test( 'reports dynamic URL literals without fetching', async () => {
	const links = new Map( [
		[ DOC_URL + '${path}', [ 'client/example.ts:1' ] ],
	] );
	const log = [];
	const results = await checkLinks( links, {
		fetcher: () => assert.fail( 'Dynamic URL must not be fetched' ),
		log: ( line ) => log.push( line ),
	} );
	assert.deepEqual(
		[ ...results.values() ],
		[ 'Dynamic URL literal needs manual review' ]
	);
	assert.ok( log.includes( '  client/example.ts:1' ) );
} );

test( 'reports every failure and escapes source text', () => {
	const links = new Map(
		Array.from( { length: 1000 }, ( _, index ) => [
			DOC_URL + index,
			[ 'client/<script>|`file`.tsx:1' ],
		] )
	);
	const results = new Map(
		[ ...links.keys() ].map( ( url ) => [ url, 'Missing anchor' ] )
	);
	const report = makeReport( links, results, 'abc123' );
	assert.equal( report.match( /Missing anchor/g ).length, 1000 );
	assert.ok(
		report.includes( 'https://woocommerce.com/document/example/999' )
	);
	assert.ok( report.includes( '&lt;script&gt;&#124;&#96;file&#96;' ) );
	assert.ok( report.startsWith( '## WooPayments documentation links' ) );
} );
