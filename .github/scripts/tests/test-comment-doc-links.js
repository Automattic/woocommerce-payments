/**
 * External dependencies
 */
const assert = require( 'node:assert/strict' );
const { execFileSync } = require( 'node:child_process' );
const {
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} = require( 'node:fs' );
const { tmpdir } = require( 'node:os' );
const { join, resolve } = require( 'node:path' );
const { test } = require( 'node:test' );

/**
 * Internal dependencies
 */
const { postReport } = require( '../comment-doc-links' );

const REPOSITORY = 'Automattic/woocommerce-payments';
const REVISION = '1234567890123456789012345678901234567890';
const RUN_URL =
	'https://github.com/Automattic/woocommerce-payments/actions/runs/123';
const MARKER = '<!-- wcpay-documentation-link-check -->';
const RELEASE_PR = {
	number: 42,
	state: 'open',
	base: { ref: 'trunk' },
	head: {
		ref: 'release/11.3.0',
		sha: REVISION,
		repo: { full_name: REPOSITORY },
	},
};

function fixture( {
	pullRequests = [ RELEASE_PR ],
	current = RELEASE_PR,
	comments = [],
} = {} ) {
	const calls = [];
	const api = ( endpoint, options = {} ) => {
		calls.push( { endpoint, ...options } );
		if ( options.method === 'POST' || options.method === 'PATCH' ) {
			return {};
		}
		if ( endpoint.endsWith( '/pulls' ) ) {
			assert.equal( options.paginate, true );
			return pullRequests;
		}
		if ( endpoint.endsWith( '/comments' ) ) {
			assert.equal( options.paginate, true );
			return comments;
		}
		assert.equal(
			endpoint,
			'repos/Automattic/woocommerce-payments/pulls/42'
		);
		return current;
	};
	const publish = ( report ) =>
		postReport( {
			repository: REPOSITORY,
			revision: REVISION,
			report,
			runUrl: RUN_URL,
			api,
		} );
	const writes = () =>
		calls.filter( ( call ) => [ 'POST', 'PATCH' ].includes( call.method ) );
	return { calls, publish, writes };
}

test( 'creates a release PR comment containing the failure report', () => {
	const { publish, writes } = fixture();
	const report =
		'## WooPayments documentation links\n\n**84 unique URLs checked; 2 failed.**\nHTTP 404; Missing anchor #example\n';
	publish( report );
	assert.deepEqual( writes(), [
		{
			endpoint:
				'repos/Automattic/woocommerce-payments/issues/42/comments',
			method: 'POST',
			body: {
				body:
					'<!-- wcpay-documentation-link-check -->\n' +
					'## WooPayments documentation links\n\n**84 unique URLs checked; 2 failed.**\nHTTP 404; Missing anchor #example\n',
			},
		},
	] );
} );

test( 'updates the existing Actions comment with a passing report without creating another', () => {
	const { publish, writes } = fixture( {
		comments: [
			{
				id: 7,
				user: { login: 'maintainer' },
				body: MARKER + '\nHuman comment',
			},
			{
				id: 8,
				user: { login: 'github-actions[bot]' },
				body: '<!-- unrelated-workflow -->',
			},
			{
				id: 9,
				user: { login: 'github-actions[bot]' },
				body: MARKER + '\nOld failure report',
			},
		],
	} );
	publish( 'All discovered documentation URLs and anchors passed.' );
	assert.deepEqual( writes(), [
		{
			endpoint: 'repos/Automattic/woocommerce-payments/issues/comments/9',
			method: 'PATCH',
			body: {
				body: '<!-- wcpay-documentation-link-check -->\nAll discovered documentation URLs and anchors passed.',
			},
		},
	] );
} );

test( 'does not overwrite human or unrelated bot comments', () => {
	const { publish, writes } = fixture( {
		comments: [
			{ id: 7, user: { login: 'maintainer' }, body: MARKER },
			{ id: 8, user: { login: 'another-bot[bot]' }, body: MARKER },
			{
				id: 9,
				user: { login: 'github-actions[bot]' },
				body: '<!-- another-check -->',
			},
		],
	} );
	publish( 'Result' );
	assert.equal( writes().length, 1 );
	assert.equal( writes()[ 0 ].method, 'POST' );
} );

test( 'skips closed, non-release, wrong-base, fork, missing-repository and outdated PRs', () => {
	const pullRequests = [
		{ ...RELEASE_PR, state: 'closed' },
		{ ...RELEASE_PR, base: { ref: 'develop' } },
		{ ...RELEASE_PR, head: { ...RELEASE_PR.head, ref: 'codex/example' } },
		{
			...RELEASE_PR,
			head: {
				...RELEASE_PR.head,
				repo: { full_name: 'contributor/woocommerce-payments' },
			},
		},
		{ ...RELEASE_PR, head: { ...RELEASE_PR.head, repo: null } },
		{ ...RELEASE_PR, head: { ...RELEASE_PR.head, sha: 'newer-commit' } },
	];
	const { publish, writes, calls } = fixture( { pullRequests } );
	publish( 'Result' );
	assert.deepEqual( writes(), [] );
	assert.equal( calls.length, 1 );
} );

test( 'does nothing when the scanned commit has no matching PR', () => {
	const { publish, writes, calls } = fixture( { pullRequests: [] } );
	publish( 'Result' );
	assert.deepEqual( writes(), [] );
	assert.equal( calls.length, 1 );
} );

test( 'rechecks the head and PR state before publishing', () => {
	for ( const current of [
		{ ...RELEASE_PR, head: { ...RELEASE_PR.head, sha: 'newer-commit' } },
		{ ...RELEASE_PR, state: 'closed' },
	] ) {
		const { publish, writes, calls } = fixture( { current } );
		publish( 'Outdated result' );
		assert.deepEqual( writes(), [] );
		assert.equal( calls.length, 3 );
	}
} );

test( 'reports a checker failure when no report was produced', () => {
	const { publish, writes } = fixture();
	publish( undefined );
	const body = writes()[ 0 ].body.body;
	assert.match( body, /The checker failed before producing its report/ );
	assert.match(
		body,
		/Checked commit: <code>1234567890123456789012345678901234567890<\/code>/
	);
	assert.ok(
		body.includes(
			'https://github.com/Automattic/woocommerce-payments/actions/runs/123'
		)
	);
} );

test( 'limits comment size at a line boundary and links the full report', () => {
	const { publish, writes } = fixture();
	const report =
		'## WooPayments documentation links\n\n**1000 unique URLs checked; 1000 failed.**\n' +
		'| https://woocommerce.com/document/example/ | Missing anchor #missing | client/example.ts:1 |\n'.repeat(
			1000
		);
	publish( report );
	const body = writes()[ 0 ].body.body;
	assert.ok( body.length <= 60000 );
	assert.ok(
		body.startsWith(
			'<!-- wcpay-documentation-link-check -->\n## WooPayments documentation links'
		)
	);
	assert.ok( body.includes( '**1000 unique URLs checked; 1000 failed.**' ) );
	assert.match( body, /client\/example.ts:1 \|\n\nReport truncated\./ );
	assert.ok(
		body.includes(
			'https://github.com/Automattic/woocommerce-payments/actions/runs/123'
		)
	);
} );

test( 'propagates GitHub API errors instead of silently losing the comment', () => {
	assert.throws(
		() =>
			postReport( {
				repository: REPOSITORY,
				revision: REVISION,
				report: 'Result',
				runUrl: RUN_URL,
				api: () => {
					throw new Error( 'GitHub API unavailable' );
				},
			} ),
		/GitHub API unavailable/
	);
} );

test( 'CLI reads the report, follows paginated responses and sends the comment as JSON through stdin', ( context ) => {
	const root = mkdtempSync( join( tmpdir(), 'wcpay-doc-comment-' ) );
	context.after( () => rmSync( root, { recursive: true, force: true } ) );
	const reportPath = join( root, 'report.md' );
	const outputPath = join( root, 'posted.json' );
	writeFileSync(
		reportPath,
		'## Checker result\nHTTP 404; Missing anchor #example\n'
	);
	const fakeGh = `#!/usr/bin/env node
const fs = require('node:fs');
const args = process.argv.slice(2);
const endpoint = args[1];
const method = args[args.indexOf('--method') + 1];
const pr = ${ JSON.stringify( RELEASE_PR ) };
if (endpoint.endsWith('/pulls')) {
  if (!args.includes('--paginate') || !args.includes('--slurp')) process.exit(2);
  process.stdout.write(JSON.stringify([[], [pr]]));
} else if (method === 'GET' && endpoint.endsWith('/comments')) {
  process.stdout.write(JSON.stringify([[], [{id: 9, user: {login: 'github-actions[bot]'}, body: ${ JSON.stringify(
		MARKER
  ) }}]]));
} else if (method === 'GET') {
  process.stdout.write(JSON.stringify(pr));
} else {
  if (args[args.indexOf('--input') + 1] !== '-') process.exit(3);
  fs.writeFileSync(process.env.TEST_POSTED_COMMENT, JSON.stringify({endpoint, method, payload: JSON.parse(fs.readFileSync(0, 'utf8'))}));
  process.stdout.write('{}');
}
`;
	writeFileSync( join( root, 'gh' ), fakeGh, { mode: 0o755 } );
	execFileSync(
		process.execPath,
		[ resolve( __dirname, '../comment-doc-links.js' ) ],
		{
			env: {
				...process.env,
				PATH: `${ root }:${ process.env.PATH }`,
				GITHUB_REPOSITORY: REPOSITORY,
				DOC_LINK_SHA: REVISION,
				DOC_LINK_REPORT: reportPath,
				DOC_LINK_RUN_URL: RUN_URL,
				TEST_POSTED_COMMENT: outputPath,
			},
		}
	);
	assert.deepEqual( JSON.parse( readFileSync( outputPath, 'utf8' ) ), {
		endpoint: 'repos/Automattic/woocommerce-payments/issues/comments/9',
		method: 'PATCH',
		payload: {
			body: '<!-- wcpay-documentation-link-check -->\n## Checker result\nHTTP 404; Missing anchor #example\n',
		},
	} );
} );
