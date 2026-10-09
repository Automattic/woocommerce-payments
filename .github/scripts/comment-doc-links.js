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

function isCurrentReleasePr( pr, repository, revision ) {
	return (
		pr.state === 'open' &&
		pr.base.ref === 'trunk' &&
		pr.head.ref.startsWith( 'release/' ) &&
		pr.head.repo?.full_name === repository &&
		pr.head.sha === revision
	);
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
	revision,
	report,
	runUrl,
	api = githubApi,
} ) {
	const prefix = `repos/${ repository }`;
	const pullRequests = api( `${ prefix }/commits/${ revision }/pulls`, {
		paginate: true,
	} );
	const body = formatComment( report, revision, runUrl );
	for ( const candidate of pullRequests ) {
		if ( ! isCurrentReleasePr( candidate, repository, revision ) ) {
			continue;
		}
		const comments = api(
			`${ prefix }/issues/${ candidate.number }/comments`,
			{ paginate: true }
		);
		const comment = comments.find(
			( entry ) =>
				entry.user?.login === 'github-actions[bot]' &&
				entry.body?.startsWith( COMMENT_MARKER )
		);
		// Recheck after listing comments: a newer push or PR closure makes this report obsolete.
		const current = api( `${ prefix }/pulls/${ candidate.number }` );
		if ( ! isCurrentReleasePr( current, repository, revision ) ) {
			continue;
		}
		if ( comment ) {
			api( `${ prefix }/issues/comments/${ comment.id }`, {
				method: 'PATCH',
				body: { body },
			} );
		} else {
			api( `${ prefix }/issues/${ candidate.number }/comments`, {
				method: 'POST',
				body: { body },
			} );
		}
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
