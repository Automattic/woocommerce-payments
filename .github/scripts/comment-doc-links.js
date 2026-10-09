#!/usr/bin/env node
/* eslint-disable no-console */

/**
 * External dependencies
 */
const { execFileSync } = require( 'node:child_process' );
const { readFileSync } = require( 'node:fs' );

const COMMENT_MARKER = '<!-- wcpay-documentation-link-check -->';
const MAX_COMMENT_LENGTH = 60000;

function githubApi(
	endpoint,
	{ method = 'GET', body, paginate = false } = {}
) {
	const args = [ 'api', endpoint, '--method', method ];
	if ( paginate ) {
		args.push( '--paginate', '--slurp' );
	}
	if ( body ) {
		args.push( '--input', '-' );
	}
	const result = JSON.parse(
		execFileSync( 'gh', args, {
			encoding: 'utf8',
			input: body ? JSON.stringify( body ) : undefined,
		} )
	);
	return paginate ? result.flat() : result;
}

function formatComment( report, revision, runUrl ) {
	const text =
		report ||
		`## WooPayments documentation links\n\nChecked commit: <code>${ revision }</code>\n\n` +
			`**The checker failed before producing its report.** See the [workflow logs](${ runUrl }).\n`;
	let body = `${ COMMENT_MARKER }\n${ text }`;
	if ( body.length > MAX_COMMENT_LENGTH ) {
		const suffix = `\n\nReport truncated. See the [complete job summary and logs](${ runUrl }).\n`;
		body =
			body.slice(
				0,
				body.lastIndexOf( '\n', MAX_COMMENT_LENGTH - suffix.length )
			) + suffix;
	}
	return body;
}

function postReport( {
	repository,
	pullRequestNumber,
	revision,
	report,
	runUrl,
	api = githubApi,
} ) {
	const prefix = `repos/${ repository }`;
	const comments = api(
		`${ prefix }/issues/${ pullRequestNumber }/comments`,
		{
			paginate: true,
		}
	);
	const comment = comments.find(
		( entry ) =>
			entry.user?.login === 'github-actions[bot]' &&
			entry.body?.startsWith( COMMENT_MARKER )
	);
	const body = formatComment( report, revision, runUrl );
	if ( comment ) {
		api( `${ prefix }/issues/comments/${ comment.id }`, {
			method: 'PATCH',
			body: { body },
		} );
	} else {
		api( `${ prefix }/issues/${ pullRequestNumber }/comments`, {
			method: 'POST',
			body: { body },
		} );
	}
}

if ( require.main === module ) {
	try {
		let report;
		try {
			report = readFileSync( process.env.DOC_LINK_REPORT, 'utf8' );
		} catch ( error ) {
			if ( error.code !== 'ENOENT' ) {
				throw error;
			}
		}
		postReport( {
			repository: process.env.GITHUB_REPOSITORY,
			pullRequestNumber: process.env.DOC_LINK_PR_NUMBER,
			revision: process.env.DOC_LINK_SHA,
			report,
			runUrl: process.env.DOC_LINK_RUN_URL,
		} );
	} catch ( error ) {
		console.error( error.message );
		process.exitCode = 1;
	}
}

module.exports = { postReport };
