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
	E2E_GH_TOKEN,
	E2E_HEAD_SHA,
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

function makeCommitUrl( sha: string ): string {
	const server = GITHUB_SERVER_URL || 'https://github.com';
	return `${ server }/${ GITHUB_REPOSITORY }/commit/${ sha }`;
}

function getBuildLogUrl(): string | undefined {
	if ( ! GITHUB_ACTIONS || ! GITHUB_RUN_ID ) {
		return undefined;
	}
	const server = GITHUB_SERVER_URL || 'https://github.com';
	const attempt = GITHUB_RUN_ATTEMPT || '1';
	return `${ server }/${ GITHUB_REPOSITORY }/actions/runs/${ GITHUB_RUN_ID }/attempts/${ attempt }`;
}

/**
 * Resolve the direct URL to the current matrix job via the GitHub API.
 * Falls back to undefined if the lookup fails for any reason.
 */
async function resolveJobUrl(): Promise< string | undefined > {
	if ( ! GITHUB_ACTIONS || ! GITHUB_RUN_ID || ! E2E_GH_TOKEN ) {
		return undefined;
	}

	try {
		const apiUrl = `https://api.github.com/repos/${ GITHUB_REPOSITORY }/actions/runs/${ GITHUB_RUN_ID }/jobs?per_page=100`;
		const response = await fetch( apiUrl, {
			headers: {
				Authorization: `token ${ E2E_GH_TOKEN }`,
				Accept: 'application/vnd.github.v3+json',
			},
			signal: AbortSignal.timeout( 5000 ),
		} );

		if ( ! response.ok ) {
			return undefined;
		}

		const data = ( await response.json() ) as {
			jobs: Array< { name: string; status: string; html_url: string } >;
		};

		// Match the current job by checking matrix env vars against job names.
		// Job names follow patterns like:
		//   "WC - latest | PHP - 8.3 | wcpay - merchant"
		//   "WP - nightly | WC - latest | subscriptions - shopper"
		const match = ( data.jobs || [] ).find( ( job ) => {
			if ( job.status !== 'in_progress' ) {
				return false;
			}
			const name = job.name;
			if (
				E2E_WP_VERSION &&
				E2E_WP_VERSION !== 'latest' &&
				! name.includes( E2E_WP_VERSION )
			) {
				return false;
			}
			if ( E2E_WC_VERSION && ! name.includes( E2E_WC_VERSION ) ) {
				return false;
			}
			if ( E2E_PHP_VERSION && ! name.includes( E2E_PHP_VERSION ) ) {
				return false;
			}
			if ( E2E_GROUP && ! name.includes( E2E_GROUP ) ) {
				return false;
			}
			if ( E2E_BRANCH && ! name.includes( E2E_BRANCH ) ) {
				return false;
			}
			return true;
		} );

		return match?.html_url;
	} catch {
		return undefined;
	}
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

function buildStatusSummary(
	failureCount: number,
	flakyCount: number
): string {
	const parts: string[] = [];
	if ( failureCount > 0 ) {
		const noun = failureCount === 1 ? 'failure' : 'failures';
		parts.push( `${ failureCount } ${ noun }` );
	}
	if ( flakyCount > 0 ) {
		parts.push( `${ flakyCount } flaky` );
	}
	return parts.join( ', ' );
}

function buildParentMessage(
	failureCount: number,
	flakyCount: number,
	done: boolean,
	buildLogUrl?: string,
	commitSha?: string
): string {
	const matrixLabel = getMatrixLabel();
	const branch = getBranch();
	const sha = commitSha || GITHUB_SHA || '';
	const shortSha = sha.substring( 0, 7 ) || 'unknown';
	const commitLink = sha
		? `<${ makeCommitUrl( sha ) }|\`${ shortSha }\`>`
		: `\`${ shortSha }\``;
	const jobTitle = buildLogUrl
		? `<${ buildLogUrl }|${ matrixLabel }>`
		: matrixLabel;

	const summary = buildStatusSummary( failureCount, flakyCount );

	if ( done ) {
		const icon = failureCount > 0 ? ':red_circle:' : ':warning:';
		return (
			`${ icon } *Done — ${ summary }* | ${ jobTitle }\n` +
			`\`${ branch }\` | ${ commitLink }`
		);
	}

	return (
		`:loading-dots: *Running* | ${ jobTitle }\n` +
		`\`${ branch }\` | ${ commitLink }\n` +
		`${ summary } so far`
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
	private flakyCount = 0;
	private enabled: boolean;
	private buildLogUrl: string | undefined;
	private commitSha: string | undefined;

	// Maps test ID → reply ts, so we can update the reply if the test recovers.
	private reportedTests = new Map< string, string >();

	constructor() {
		this.enabled = isEnabled();
		this.client = new SlackClient(
			E2E_SLACK_TOKEN || '',
			E2E_SLACK_CHANNEL_ID || ''
		);
		this.buildLogUrl = getBuildLogUrl();
		this.commitSha = E2E_HEAD_SHA || GITHUB_SHA || undefined;
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

		const isRetry = result.retry > 0;
		const wasReported = this.reportedTests.has( test.id );

		// Retry of a previously reported test — check if it recovered.
		// Use result.status directly instead of test.outcome() to avoid
		// depending on whether Playwright has recorded this result yet.
		if ( isRetry && wasReported && result.status === 'passed' ) {
			await this.markTestAsFlaky( test );
			return;
		}

		// Only report first-attempt unexpected failures.
		if ( isRetry || test.outcome() !== 'unexpected' ) {
			return;
		}

		this.failureCount++;

		// First failure: resolve job URL, create the parent thread.
		if ( ! this.threadTs ) {
			await this.client.joinChannel();
			const jobUrl = await resolveJobUrl();
			this.buildLogUrl = jobUrl ?? this.buildLogUrl;
			this.threadTs = await this.client.postMessage(
				buildParentMessage(
					this.failureCount,
					this.flakyCount,
					false,
					this.buildLogUrl,
					this.commitSha
				)
			);
		} else {
			// Update the parent message with the new count.
			await this.client.updateMessage(
				this.threadTs,
				buildParentMessage(
					this.failureCount,
					this.flakyCount,
					false,
					this.buildLogUrl,
					this.commitSha
				)
			);
		}

		if ( ! this.threadTs ) {
			// If we still don't have a threadTs, posting failed — bail.
			return;
		}

		// Post failure details as a threaded reply.
		const testTitle = test.titlePath().join( ' › ' );
		const errorMsg = result.errors?.[ 0 ]?.message;
		const replyTs = await this.client.postReply(
			this.threadTs,
			buildFailureReply( testTitle, errorMsg )
		);

		// Track the reply so we can update it if the test recovers on retry.
		// Store even if replyTs is missing so failureCount is still adjusted.
		this.reportedTests.set( test.id, replyTs ?? '' );

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
		if (
			! this.enabled ||
			! this.threadTs ||
			( this.failureCount === 0 && this.flakyCount === 0 )
		) {
			return;
		}

		// Final update: mark the parent message as done.
		await this.client.updateMessage(
			this.threadTs,
			buildParentMessage(
				this.failureCount,
				this.flakyCount,
				true,
				this.buildLogUrl,
				this.commitSha
			)
		);
	}

	/**
	 * A test that was reported as failed has now passed on retry.
	 * Update the reply to show it recovered, and adjust the counts.
	 */
	private async markTestAsFlaky( test: TestCase ): Promise< void > {
		this.failureCount--;
		this.flakyCount++;

		const replyTs = this.reportedTests.get( test.id );
		if ( replyTs && this.threadTs ) {
			const testTitle = test.titlePath().join( ' › ' );
			await this.client.updateMessage(
				replyTs,
				`:recycle: ~${ testTitle }~ passed on retry`
			);
		}

		// Update the parent message with adjusted counts.
		if ( this.threadTs ) {
			await this.client.updateMessage(
				this.threadTs,
				buildParentMessage(
					this.failureCount,
					this.flakyCount,
					false,
					this.buildLogUrl,
					this.commitSha
				)
			);
		}
	}
}

export default SlackReporter;
