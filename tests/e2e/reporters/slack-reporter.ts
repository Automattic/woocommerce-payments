/* eslint-disable @typescript-eslint/naming-convention */

/**
 * External dependencies
 */
import type {
	Reporter,
	FullConfig,
	Suite,
	TestCase,
	TestResult,
	FullResult,
} from '@playwright/test/reporter';

/**
 * Internal dependencies
 */
import { SlackClient } from '../utils/slack';

// -- Env vars -----------------------------------------------------------------

const {
	E2E_SLACK_TOKEN,
	E2E_SLACK_CHANNEL_ID,
	WC_E2E_SCREENSHOTS,
	// Matrix context
	E2E_WC_VERSION,
	E2E_PHP_VERSION,
	E2E_WP_VERSION,
	E2E_GROUP,
	E2E_BRANCH,
	// GitHub context
	GITHUB_ACTIONS,
	GITHUB_REF,
	GITHUB_HEAD_REF,
	GITHUB_SHA,
	GITHUB_SERVER_URL,
	GITHUB_REPOSITORY,
	GITHUB_RUN_ID,
	GITHUB_RUN_ATTEMPT,
	GITHUB_WORKFLOW,
} = process.env;

// -- Helpers ------------------------------------------------------------------

function isEnabled(): boolean {
	return (
		!! WC_E2E_SCREENSHOTS && !! E2E_SLACK_TOKEN && !! E2E_SLACK_CHANNEL_ID
	);
}

function getBranch(): string {
	if ( ! GITHUB_ACTIONS ) {
		return 'local environment';
	}
	const ref = GITHUB_REF || '';
	if ( ref.startsWith( 'refs/heads/' ) ) {
		return ref.replace( 'refs/heads/', '' );
	}
	if ( ref.startsWith( 'refs/pull/' ) ) {
		return GITHUB_HEAD_REF || 'pull-request';
	}
	if ( ref.startsWith( 'refs/tags/' ) ) {
		return ref.replace( 'refs/tags/', '' );
	}
	return 'unknown';
}

function getCommitShort(): string {
	if ( ! GITHUB_ACTIONS ) {
		return 'latest';
	}
	return ( GITHUB_SHA || 'unknown' ).substring( 0, 7 );
}

function getBuildLogUrl(): string | undefined {
	if ( ! GITHUB_ACTIONS || ! GITHUB_RUN_ID ) {
		return undefined;
	}
	const server = GITHUB_SERVER_URL || 'https://github.com';
	const attempt = GITHUB_RUN_ATTEMPT || '1';
	return `${ server }/${ GITHUB_REPOSITORY }/actions/runs/${ GITHUB_RUN_ID }/attempts/${ attempt }`;
}

function getMatrixLabel(): string {
	const parts: string[] = [];
	if ( E2E_WP_VERSION && E2E_WP_VERSION !== 'latest' ) {
		parts.push( `WP ${ E2E_WP_VERSION }` );
	}
	if ( E2E_WC_VERSION ) {
		parts.push( `WC ${ E2E_WC_VERSION }` );
	}
	if ( E2E_PHP_VERSION ) {
		parts.push( `PHP ${ E2E_PHP_VERSION }` );
	}
	if ( E2E_GROUP ) {
		const branchSuffix = E2E_BRANCH ? ` ${ E2E_BRANCH }` : '';
		parts.push( `${ E2E_GROUP }${ branchSuffix }` );
	}
	return parts.join( ' | ' );
}

function truncate( text: string, maxLength: number ): string {
	if ( text.length <= maxLength ) {
		return text;
	}
	return text.substring( 0, maxLength ) + '…';
}

function slugifyForFileName( input: string ): string {
	return input
		.toLowerCase()
		.replace( /[^\w\d-]+/g, '-' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' );
}

/**
 * Strip ANSI color/escape codes from a string so Slack messages are readable.
 */
function stripAnsi( text: string ): string {
	// eslint-disable-next-line no-control-regex
	return text.replace( /\u001B\[[0-9;]*m/g, '' );
}

// -- Message builders ---------------------------------------------------------

function buildParentMessage( failureCount: number, done: boolean ): string {
	const matrixLabel = getMatrixLabel();
	const branch = getBranch();
	const commit = getCommitShort();
	const buildLogUrl = getBuildLogUrl();
	const buildLogLink = buildLogUrl ? ` | <${ buildLogUrl }|Build Log>` : '';
	const workflow = GITHUB_WORKFLOW ? ` (${ GITHUB_WORKFLOW })` : '';

	const noun = failureCount === 1 ? 'failure' : 'failures';

	if ( done ) {
		return (
			`:octagonal_sign: *Done — ${ failureCount } ${ noun }* | ${ matrixLabel }\n` +
			`Branch: ${ branch } | Commit: ${ commit }${ workflow }${ buildLogLink }`
		);
	}

	return (
		`:red_circle: *Running* | ${ matrixLabel }\n` +
		`Branch: ${ branch } | Commit: ${ commit }${ workflow }\n` +
		`${ failureCount } ${ noun } so far${ buildLogLink }`
	);
}

function buildFailureReply( testTitle: string, errorMessage?: string ): string {
	let text = `:x: *${ testTitle }*`;
	if ( errorMessage ) {
		const cleaned = stripAnsi( errorMessage.split( '\n' )[ 0 ] );
		text += `\n> ${ truncate( cleaned, 300 ) }`;
	}
	return text;
}

// -- Reporter -----------------------------------------------------------------

class SlackReporter implements Reporter {
	private client: SlackClient;
	private threadTs: string | undefined;
	private failureCount = 0;
	private enabled: boolean;

	constructor() {
		this.enabled = isEnabled();
		this.client = new SlackClient(
			E2E_SLACK_TOKEN || '',
			E2E_SLACK_CHANNEL_ID || ''
		);
	}

	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	onBegin( config: FullConfig, suite: Suite ) {
		// Join the channel once at the start (non-blocking fire-and-forget).
		if ( this.enabled ) {
			this.client.joinChannel();
		}
	}

	async onTestEnd( test: TestCase, result: TestResult ) {
		if ( ! this.enabled ) {
			return;
		}

		// Skip retries and non-failures.
		if ( result.retry !== 0 ) {
			return;
		}
		if ( test.outcome() !== 'unexpected' ) {
			return;
		}

		this.failureCount++;

		// First failure: create the parent thread message.
		if ( ! this.threadTs ) {
			await this.client.joinChannel();
			this.threadTs = await this.client.postMessage(
				buildParentMessage( this.failureCount, false )
			);
		} else {
			// Update the parent message with the new count.
			await this.client.updateMessage(
				this.threadTs,
				buildParentMessage( this.failureCount, false )
			);
		}

		if ( ! this.threadTs ) {
			// If we still don't have a threadTs, posting failed — bail.
			return;
		}

		// Post failure details as a threaded reply.
		const testTitle = test.titlePath().join( ' › ' );
		const errorMsg = result.errors?.[ 0 ]?.message;
		await this.client.postReply(
			this.threadTs,
			buildFailureReply( testTitle, errorMsg )
		);

		// Upload screenshot as a threaded reply.
		const screenshots = result.attachments.filter(
			( { name, path } ) => name === 'screenshot' && path
		);
		if ( screenshots.length > 0 && screenshots[ 0 ].path ) {
			await this.client.uploadScreenshot(
				this.threadTs,
				screenshots[ 0 ].path,
				`screenshot_of_${ slugifyForFileName( test.title ) }.png`
			);
		}
	}

	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	async onEnd( result: FullResult ) {
		if ( ! this.enabled || ! this.threadTs || this.failureCount === 0 ) {
			return;
		}

		// Final update: mark the parent message as done.
		await this.client.updateMessage(
			this.threadTs,
			buildParentMessage( this.failureCount, true )
		);
	}
}

export default SlackReporter;
