/* eslint-disable no-console */
/* eslint-disable @typescript-eslint/naming-convention */

/**
 * External dependencies
 */
import { createReadStream } from 'fs';
import {
	WebClient,
	ErrorCode,
	CodedError,
	WebAPICallResult,
} from '@slack/web-api';

/**
 * This integration was adapted from @woocommerce/e2e-environment.
 * https://github.com/woocommerce/woocommerce/blob/trunk/packages/js/e2e-environment/src/slack/reporter.js
 */

const {
	GITHUB_ACTIONS,
	GITHUB_REF,
	GITHUB_SHA,
	GITHUB_REPOSITORY,
	GITHUB_RUN_ID,
	E2E_SLACK_TOKEN,
	E2E_SLACK_CHANNEL_ID,
	WC_E2E_SCREENSHOTS,
} = process.env;

interface SlackParams {
	branch: string;
	commit: string;
	webUrl?: string;
}

interface ErrorWithData extends CodedError {
	data: WebAPICallResult & { error: string };
}

const errorsWithData = [
	ErrorCode.FileUploadInvalidArgumentsError,
	ErrorCode.PlatformError,
];

let web: WebClient;

/**
 * Initialize the Slack web client.
 */
const initializeWeb = (): WebClient => {
	if ( ! web ) {
		web = new WebClient( E2E_SLACK_TOKEN );
	}
	return web;
};

/**
 * Initialize Slack parameters if tests are running in CI.
 */
const initializeSlack = (): SlackParams | undefined => {
	if ( ! WC_E2E_SCREENSHOTS || ! E2E_SLACK_TOKEN ) {
		return;
	}

	if ( ! GITHUB_ACTIONS ) {
		return {
			branch: 'local environment',
			commit: 'latest',
		};
	}

	// Build PR info
	const refArray = GITHUB_REF.split( '/' );
	const branch = refArray.pop();

	return {
		branch,
		commit: GITHUB_SHA,
		webUrl: `https://github.com/${ GITHUB_REPOSITORY }/actions/runs/${ GITHUB_RUN_ID }`,
	};
};

const handleRequestError = (
	error: unknown,
	errorMessage = 'An unexpected error occurred'
) => {
	if ( errorsWithData.includes( ( error as CodedError ).code ) ) {
		console.log( errorMessage, ( error as ErrorWithData ).data );
	} else {
		console.log( errorMessage, error );
	}
};

/**
 * Post a message to a Slack channel for a failed test.
 */
export const sendFailedTestMessageToSlack = async ( testName: string ) => {
	const slackParams = initializeSlack();

	if ( ! slackParams?.branch ) {
		return;
	}

	const { branch, commit, webUrl } = slackParams;
	const webClient = initializeWeb();

	try {
		// Adding the app does not add the app user to the channel
		await webClient.conversations.join( {
			channel: E2E_SLACK_CHANNEL_ID,
			token: E2E_SLACK_TOKEN,
		} );
	} catch ( error ) {
		handleRequestError( error, 'Failed to join the channel' );
	}

	try {
		await webClient.chat.postMessage( {
			channel: E2E_SLACK_CHANNEL_ID,
			token: E2E_SLACK_TOKEN,
			text: `Test failed on *${ branch }* branch. \n
            The commit this build is testing is *${ commit }*. \n
            The name of the test that failed: *${ testName }*. \n
            See screenshot of the failed test below. ${
				webUrl ? `*Build log* can be found here: ${ webUrl }` : ''
			}`,
		} );
	} catch ( error ) {
		handleRequestError( error, 'Failed to post message to Slack' );
	}
};

/**
 * Post a screenshot to a Slack channel for a failed test.
 */
export const sendFailedTestScreenshotToSlack = async (
	screenshotOfFailedTest: string
) => {
	if ( ! initializeSlack() ) {
		return;
	}

	const filename = 'screenshot_of_failed_test.png';
	const webClient = initializeWeb();

	try {
		await webClient.filesUploadV2( {
			filename,
			file: createReadStream( screenshotOfFailedTest ),
			token: E2E_SLACK_TOKEN,
			channel_id: E2E_SLACK_CHANNEL_ID,
		} );
	} catch ( error ) {
		handleRequestError( error, 'Failed to upload screenshot to Slack' );
	}
};
