/* eslint-disable @typescript-eslint/naming-convention */
/* eslint-disable no-console */

/**
 * External dependencies
 */
import { writeFileSync, readFileSync, existsSync } from 'fs';
import { resolve } from 'path';
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
	// When set, the reporter is running in the phase-2 re-run and reconciles
	// the phase-1 message instead of posting a new one.
	E2E_SLACK_RERUN,
	// Optional override for the cross-phase state file path.
	E2E_SLACK_STATE_FILE,
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

// -- Re-run state (phase-1 → phase-2 handoff) --------------------------------

interface RerunState {
	threadTs: string;
	buildLogUrl?: string;
	failedTestIds: string[];
}

function getStateFilePath(): string {
	return (
		E2E_SLACK_STATE_FILE ||
		resolve( __dirname, '../.slack-thread-state.json' )
	);
}

function writeRerunState( state: RerunState ): void {
	try {
		writeFileSync( getStateFilePath(), JSON.stringify( state ), 'utf8' );
	} catch ( error ) {
		// Non-fatal: a missing state file just means phase 2 falls back to
		// its default behavior.
		console.log( 'Failed to write Slack re-run state', error );
	}
}

function readRerunState(): RerunState | undefined {
	try {
		const filePath = getStateFilePath();
		if ( ! existsSync( filePath ) ) {
			return undefined;
		}
		const parsed = JSON.parse( readFileSync( filePath, 'utf8' ) );
		// Guard against a parseable-but-malformed file so reconciliation
		// can't later throw on a non-iterable failedTestIds.
		if (
			typeof parsed?.threadTs !== 'string' ||
			! Array.isArray( parsed?.failedTestIds )
		) {
			return undefined;
		}
		return parsed as RerunState;
	} catch ( error ) {
		// Non-fatal: treat a corrupt/unreadable state file as "no state".
		console.log( 'Failed to read Slack re-run state', error );
		return undefined;
	}
}

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

function buildHeaderParts(
	buildLogUrl?: string,
	commitSha?: string
): { jobTitle: string; commitLine: string } {
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
	return { jobTitle, commitLine: `\`${ branch }\` | ${ commitLink }` };
}

function buildParentMessage(
	failureCount: number,
	flakyCount: number,
	done: boolean,
	buildLogUrl?: string,
	commitSha?: string
): string {
	const { jobTitle, commitLine } = buildHeaderParts( buildLogUrl, commitSha );
	const summary = buildStatusSummary( failureCount, flakyCount );

	if ( done ) {
		const icon = failureCount > 0 ? ':red_circle:' : ':warning:';
		return (
			`${ icon } *Done — ${ summary }* | ${ jobTitle }\n` +
			`${ commitLine }`
		);
	}

	return (
		`:loading-dots: *Running* | ${ jobTitle }\n` +
		`${ commitLine }\n` +
		`${ summary } so far`
	);
}

/**
 * Build the reconciled parent message posted after the phase-2 re-run.
 * - All recovered → yellow "passed after re-run".
 * - Some still failing → red, annotated with the recovered count.
 */
function buildRerunParentMessage(
	stillFailingCount: number,
	recoveredCount: number,
	buildLogUrl?: string,
	commitSha?: string
): string {
	const { jobTitle, commitLine } = buildHeaderParts( buildLogUrl, commitSha );

	if ( stillFailingCount > 0 ) {
		const noun = stillFailingCount === 1 ? 'failure' : 'failures';
		const recoveredNote =
			recoveredCount > 0
				? ` (${ recoveredCount } recovered on re-run)`
				: '';
		return (
			`:red_circle: *Done — ${ stillFailingCount } ${ noun }*` +
			`${ recoveredNote } | ${ jobTitle }\n` +
			`${ commitLine }`
		);
	}

	return (
		`:large_yellow_circle: *Done — passed after re-run* ` +
		`(${ recoveredCount } recovered) | ${ jobTitle }\n` +
		`${ commitLine }`
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

	// Test IDs currently counted as failures (entries are dropped on recovery).
	private failedTestIds = new Set< string >();

	// Re-run mode (phase 2): adopt the phase-1 thread and reconcile it.
	private rerun = false;
	private rerunState: RerunState | undefined;
	// Maps test ID → final status observed during the phase-2 re-run.
	private phase2Status = new Map< string, TestResult[ 'status' ] >();

	constructor() {
		this.enabled = isEnabled();
		this.client = new SlackClient(
			E2E_SLACK_TOKEN || '',
			E2E_SLACK_CHANNEL_ID || ''
		);
		this.buildLogUrl = getBuildLogUrl();
		this.commitSha = E2E_HEAD_SHA || GITHUB_SHA || undefined;

		// In the phase-2 re-run, adopt the phase-1 thread (and its job URL) so
		// we reconcile that message rather than create a new one.
		this.rerun = !! E2E_SLACK_RERUN;
		if ( this.rerun ) {
			this.rerunState = readRerunState();
			if ( this.rerunState ) {
				this.threadTs = this.rerunState.threadTs;
				this.buildLogUrl =
					this.rerunState.buildLogUrl ?? this.buildLogUrl;
			} else {
				// Re-run requested but no phase-1 state to adopt: fall back to
				// posting a fresh thread (pre-reconciliation behavior).
				console.log(
					'Slack re-run requested but no phase-1 state found; ' +
						'falling back to a new thread.'
				);
			}
		}
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

		// Re-run mode (phase 2): don't post anything. Record each test's final
		// status so onEnd can reconcile the phase-1 parent message.
		if ( this.rerun && this.rerunState ) {
			this.phase2Status.set( test.id, result.status );
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
		this.failedTestIds.add( test.id );

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
		if ( ! this.enabled ) {
			return;
		}

		// Re-run mode (phase 2): reconcile the phase-1 parent against the
		// re-run outcomes instead of posting a separate message.
		if ( this.rerun && this.rerunState ) {
			await this.reconcileAfterRerun();
			return;
		}

		if (
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

		// Persist state so a phase-2 re-run can reconcile THIS message
		// (recover or downgrade it) instead of orphaning or duplicating it.
		if ( this.failureCount > 0 ) {
			this.persistRerunState();
		}
	}

	/**
	 * A test that was reported as failed has now passed on retry.
	 * Update the reply to show it recovered, and adjust the counts.
	 */
	private async markTestAsFlaky( test: TestCase ): Promise< void > {
		this.failureCount--;
		this.flakyCount++;
		this.failedTestIds.delete( test.id );

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

	/**
	 * Persist what a phase-2 re-run needs to reconcile this job's parent
	 * message: the thread ts, the job URL, and the IDs still counted as
	 * failures (tests that recovered in phase 1 are already dropped).
	 */
	private persistRerunState(): void {
		if ( ! this.threadTs ) {
			return;
		}
		writeRerunState( {
			threadTs: this.threadTs,
			buildLogUrl: this.buildLogUrl,
			failedTestIds: Array.from( this.failedTestIds ),
		} );
	}

	/**
	 * Reconcile the phase-1 parent message against the phase-2 re-run.
	 * A phase-1 failure is "recovered" only if it passed in the re-run;
	 * anything else (failed again, or never re-run) stays a failure.
	 */
	private async reconcileAfterRerun(): Promise< void > {
		const state = this.rerunState;
		if ( ! state || ! this.threadTs ) {
			return;
		}

		const phase1Failures = new Set( state.failedTestIds );
		let recovered = 0;
		let stillFailing = 0;
		for ( const id of phase1Failures ) {
			if ( this.phase2Status.get( id ) === 'passed' ) {
				recovered++;
			} else {
				stillFailing++;
			}
		}

		// Phase 2 re-runs whole spec files, so it can surface a test that
		// passed in phase 1 but fails now. Those make the job red under
		// `set -e`, so count them too — otherwise the reconciled message
		// could look greener than the actual job outcome.
		for ( const [ id, status ] of this.phase2Status ) {
			if (
				! phase1Failures.has( id ) &&
				status !== 'passed' &&
				status !== 'skipped'
			) {
				stillFailing++;
			}
		}

		await this.client.updateMessage(
			this.threadTs,
			buildRerunParentMessage(
				stillFailing,
				recovered,
				this.buildLogUrl,
				this.commitSha
			)
		);
	}
}

export default SlackReporter;
