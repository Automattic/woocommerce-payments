#!/usr/bin/env node
/* eslint-disable no-console */

/**
 * External dependencies
 */
const { execFileSync } = require( 'node:child_process' );
const {
	appendFileSync,
	lstatSync,
	readFileSync,
	writeFileSync,
} = require( 'node:fs' );
const { extname, resolve } = require( 'node:path' );
const { setTimeout: sleep } = require( 'node:timers/promises' );
const { parseArgs } = require( 'node:util' );
const { parse, parseFragment } = require( 'parse5' );

const SOURCE_DIRECTORIES = new Set( [
	'assets',
	'client',
	'includes',
	'src',
	'templates',
] );
const SOURCE_EXTENSIONS = new Set( [ '.php', '.js', '.jsx', '.ts', '.tsx' ] );
const EXCLUDED_DIRECTORIES = new Set( [
	'test',
	'tests',
	'__tests__',
	'__snapshots__',
	'node_modules',
	'vendor',
	'dist',
] );
const DOC_HOSTS = new Set( [
	'woocommerce.com',
	'www.woocommerce.com',
	'docs.woocommerce.com',
	'developer.woocommerce.com',
] );
const URL_PATTERN =
	/https?:\/\/(?:(?:www\.)?woocommerce\.com\/(?:document(?:ation)?|docs)(?=[/?#]|$)|docs\.woocommerce\.com\/)[^\s"'`<>\\]*/gi;
const MAX_HTML_BYTES = 10 * 1024 * 1024;
const REDIRECT_STATUSES = new Set( [ 301, 302, 303, 307, 308 ] );

class VerificationError extends Error {
	constructor( message, retryable = false ) {
		super( message );
		this.retryable = retryable;
	}
}

function validateDestination( url, hosts ) {
	let parsed;
	try {
		parsed = new URL( url );
	} catch {
		throw new VerificationError( `Invalid URL: ${ url }` );
	}
	if (
		! [ 'http:', 'https:' ].includes( parsed.protocol ) ||
		! hosts.has( parsed.hostname ) ||
		parsed.username ||
		parsed.password
	) {
		throw new VerificationError(
			`Destination is not a WooCommerce documentation host: ${ url }`
		);
	}
}

function isSource( filename ) {
	if ( filename === 'readme.txt' ) {
		return true;
	}
	const parts = filename.split( '/' );
	return (
		SOURCE_EXTENSIONS.has( extname( filename ) ) &&
		( parts.length === 1 || SOURCE_DIRECTORIES.has( parts[ 0 ] ) ) &&
		! parts.some( ( part ) => EXCLUDED_DIRECTORIES.has( part ) ) &&
		! /\.(?:test|spec|min)\./.test( parts.at( -1 ) )
	);
}

function discoverLinks( root ) {
	const tracked = execFileSync( 'git', [ 'ls-files', '-z' ], {
		cwd: root,
		encoding: 'utf8',
	} );
	const links = new Map();
	for ( const filename of tracked.split( '\0' ).filter( Boolean ).sort() ) {
		const path = resolve( root, filename );
		if ( ! isSource( filename ) || lstatSync( path ).isSymbolicLink() ) {
			continue;
		}
		readFileSync( path, 'utf8' )
			.split( /\r?\n/ )
			.forEach( ( sourceLine, index ) => {
				// JSON-style escaped slashes also occur in PHP/JS string literals.
				const line = sourceLine.replaceAll( '\\/', '/' );
				for ( const match of line.matchAll( URL_PATTERN ) ) {
					// Matches contain no markup; parsing this text decodes HTML entities.
					let url = parseFragment( match[ 0 ] )
						.childNodes.map( ( node ) => node.value )
						.join( '' );
					if (
						! [ "'", '"', '`', '\\' ].includes(
							line[ match.index + match[ 0 ].length ]
						)
					) {
						url = url.replace( /[.,;)\]]+$/, '' );
					}
					if ( ! links.has( url ) ) {
						links.set( url, [] );
					}
					links.get( url ).push( `${ filename }:${ index + 1 }` );
				}
			} );
	}
	return links;
}

function collectAnchors( html ) {
	const anchors = new Set();
	const pending = [ parse( html ) ];
	while ( pending.length ) {
		const node = pending.pop();
		for ( const attribute of node.attrs || [] ) {
			if ( attribute.name === 'id' ) {
				anchors.add( attribute.value );
			}
		}
		pending.push( ...( node.childNodes || [] ) );
		if ( node.content ) {
			pending.push( node.content );
		}
	}
	return anchors;
}

async function requestPage( url, { timeout, hosts } ) {
	// null preserves the original fragment; an empty string explicitly clears it.
	let fragment = null;
	for ( let redirects = 0; redirects <= 10; redirects++ ) {
		validateDestination( url, hosts );
		const response = await fetch( url, {
			redirect: 'manual',
			signal: AbortSignal.timeout( timeout ),
			headers: {
				'User-Agent': 'WooPayments-documentation-link-checker/1.0',
				Accept: 'text/html,application/xhtml+xml',
			},
		} );
		if ( REDIRECT_STATUSES.has( response.status ) ) {
			await response.body?.cancel();
			const location = response.headers.get( 'location' );
			if ( location === null ) {
				throw new VerificationError(
					'Redirect has no Location header'
				);
			}
			url = new URL( location, url ).href;
			if ( location.includes( '#' ) ) {
				fragment = decodeURIComponent( new URL( url ).hash.slice( 1 ) );
			}
			continue;
		}
		if ( ! response.ok ) {
			await response.body?.cancel();
			throw new VerificationError(
				`HTTP ${ response.status } (${ response.statusText })`,
				[ 408, 429 ].includes( response.status ) ||
					response.status >= 500
			);
		}
		const contentType = response.headers.get( 'content-type' ) || '';
		if (
			! [ 'text/html', 'application/xhtml+xml' ].includes(
				contentType.split( ';' )[ 0 ].trim().toLowerCase()
			)
		) {
			await response.body?.cancel();
			throw new VerificationError( 'Response is not HTML' );
		}
		const chunks = [];
		let size = 0;
		for await ( const chunk of response.body || [] ) {
			size += chunk.length;
			if ( size > MAX_HTML_BYTES ) {
				throw new VerificationError( 'HTML exceeds the 10 MiB limit' );
			}
			chunks.push( chunk );
		}
		const charset =
			/charset\s*=\s*["']?([^\s;"']+)/i.exec( contentType )?.[ 1 ] ||
			'utf-8';
		const html = new TextDecoder( charset ).decode(
			Buffer.concat( chunks )
		);
		return { url, anchors: collectAnchors( html ), error: '', fragment };
	}
	throw new VerificationError( 'Too many redirects' );
}

async function fetchPage(
	url,
	{ timeout = 20000, attempts = 3, hosts = DOC_HOSTS, retryDelay = 1000 } = {}
) {
	let error;
	for ( let attempt = 0; attempt < attempts; attempt++ ) {
		try {
			return await requestPage( url, { timeout, hosts } );
		} catch ( cause ) {
			error =
				cause instanceof VerificationError
					? cause.message
					: `Request failed: ${ cause.message }`;
			if (
				( cause instanceof VerificationError && ! cause.retryable ) ||
				cause instanceof RangeError ||
				cause instanceof URIError
			) {
				break;
			}
		}
		if ( attempt + 1 < attempts ) {
			await sleep( retryDelay * 2 ** attempt );
		}
	}
	return {
		url,
		anchors: new Set(),
		error: error.replace( /\s+/g, ' ' ),
		fragment: null,
	};
}

async function checkLinks(
	links,
	{ fetcher = fetchPage, log = console.log } = {}
) {
	const pages = new Map();
	const results = new Map();
	const record = ( url, error ) => {
		results.set( url, error );
		log(
			`${ error ? 'FAIL' : 'PASS' } ${ url }${
				error ? ` — ${ error.replace( /\s+/g, ' ' ) }` : ''
			}`
		);
		links.get( url ).forEach( ( location ) => log( `  ${ location }` ) );
	};
	for ( const url of [ ...links.keys() ].sort() ) {
		if ( /\$|\{|\}|%(?:\d+\$)?s/.test( url ) ) {
			record( url, 'Dynamic URL literal needs manual review' );
			continue;
		}
		const base = url.split( '#' )[ 0 ];
		if ( ! pages.has( base ) ) {
			pages.set( base, [] );
		}
		pages.get( base ).push( url );
	}
	const pending = pages.entries();
	await Promise.all(
		Array.from( { length: 4 }, async () => {
			for ( const [ base, urls ] of pending ) {
				const page = await fetcher( base );
				for ( const url of urls ) {
					let error = page.error;
					if ( ! error ) {
						try {
							const fragment =
								page.fragment ??
								decodeURIComponent(
									new URL( url ).hash.slice( 1 )
								);
							if ( fragment && ! page.anchors.has( fragment ) ) {
								error = `Missing anchor #${ fragment } on ${
									page.url.split( '#' )[ 0 ]
								}`;
							}
						} catch ( cause ) {
							error = `Invalid URL fragment: ${ cause.message }`;
						}
					}
					record( url, error );
				}
			}
		} )
	);
	return results;
}

function code( value ) {
	const entities = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;',
		'|': '&#124;',
		'`': '&#96;',
	};
	return `<code>${ String( value ).replace(
		/[&<>"'|`]/g,
		( character ) => entities[ character ]
	) }</code>`;
}

function makeReport( links, results, revision, runUrl = '' ) {
	const failures = [ ...results ]
		.filter( ( [ , error ] ) => error )
		.sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) );
	const lines = [
		'## WooPayments documentation links',
		'',
		`Checked commit: ${ code( revision ) }`,
		'',
	];
	if ( runUrl ) {
		lines.push( `[Workflow run and full logs](${ runUrl })`, '' );
	}
	lines.push(
		`**${ results.size } unique URLs checked; ${ failures.length } failed.**`,
		''
	);
	if ( ! links.size ) {
		lines.push(
			'**Failed: no documentation URLs were found. Check the scan scope.**',
			''
		);
	}
	lines.push(
		'Scope: readme.txt and tracked PHP, JS, JSX, TS and TSX plugin source; tests, dependencies and generated bundles excluded.',
		'Checks complete URL literals, including source comments. Runtime expressions are not evaluated.',
		'A successful response and an existing ID do not establish that the destination covers the correct topic.',
		'Request errors (including access blocks or timeouts) are failures to verify, not proof of a broken link.',
		''
	);
	if ( failures.length ) {
		lines.push( '| URL | Problem | Source |', '| --- | --- | --- |' );
		for ( const [ url, error ] of failures ) {
			lines.push(
				`| ${ code( url ) } | ${ code( error ) } | ${ links
					.get( url )
					.map( code )
					.join( '<br>' ) } |`
			);
		}
	} else if ( links.size ) {
		lines.push( 'All discovered documentation URLs and anchors passed.' );
	}
	return lines.join( '\n' ) + '\n';
}

async function main() {
	const { values } = parseArgs( {
		options: {
			root: { type: 'string', default: process.cwd() },
			report: { type: 'string' },
		},
	} );
	if ( ! values.report ) {
		throw new Error(
			'Usage: node check-doc-links.js [--root PATH] --report PATH'
		);
	}
	const root = resolve( values.root );
	const revision = execFileSync( 'git', [ 'rev-parse', 'HEAD' ], {
		cwd: root,
		encoding: 'utf8',
	} ).trim();
	const links = discoverLinks( root );
	console.log(
		`Found ${ links.size } unique documentation URLs at commit ${ revision }.`
	);
	const results = await checkLinks( links );
	const report = makeReport(
		links,
		results,
		revision,
		process.env.DOC_LINK_RUN_URL
	);
	writeFileSync( values.report, report );
	if ( process.env.GITHUB_STEP_SUMMARY ) {
		appendFileSync( process.env.GITHUB_STEP_SUMMARY, report );
	}
	const failures = [ ...results.values() ].filter( Boolean ).length;
	console.log( `Checked ${ results.size } URLs; ${ failures } failed.` );
	return ! links.size || failures ? 1 : 0;
}

if ( require.main === module ) {
	main()
		.then( ( exitCode ) => {
			process.exitCode = exitCode;
		} )
		.catch( ( error ) => {
			console.error( error.message );
			process.exitCode = 1;
		} );
}

module.exports = {
	discoverLinks,
	collectAnchors,
	fetchPage,
	checkLinks,
	makeReport,
};
