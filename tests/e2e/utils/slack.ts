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

interface ErrorWithData extends CodedError {
	data: WebAPICallResult & { error: string };
}

const errorsWithData = [
	ErrorCode.FileUploadInvalidArgumentsError,
	ErrorCode.PlatformError,
];

function handleRequestError(
	error: unknown,
	errorMessage = 'An unexpected error occurred'
) {
	if ( errorsWithData.includes( ( error as CodedError ).code ) ) {
		console.log( errorMessage, ( error as ErrorWithData ).data );
	} else {
		console.log( errorMessage, error );
	}
}

/**
 * SlackClient wraps the @slack/web-api WebClient and provides
 * high-level operations for the E2E Slack reporter.
 *
 * One instance per reporter (= one per matrix combination).
 */
export class SlackClient {
	private web: WebClient | null = null;
	private token: string;
	private channelId: string;
	private hasJoined = false;

	constructor( token: string, channelId: string ) {
		this.token = token;
		this.channelId = channelId;
	}

	/**
	 * Returns true if the client is configured (has token + channel).
	 */
	isConfigured(): boolean {
		return !! this.token && !! this.channelId;
	}

	private getWeb(): WebClient {
		if ( ! this.web ) {
			this.web = new WebClient( this.token );
		}
		return this.web;
	}

	/**
	 * Join the Slack channel. Called once; subsequent calls are no-ops.
	 */
	async joinChannel(): Promise< void > {
		if ( this.hasJoined ) {
			return;
		}

		try {
			await this.getWeb().conversations.join( {
				channel: this.channelId,
				token: this.token,
			} );
			this.hasJoined = true;
		} catch ( error ) {
			// already_in_channel is fine — mark as joined.
			if (
				( error as CodedError ).code === ErrorCode.PlatformError &&
				( error as ErrorWithData ).data?.error === 'already_in_channel'
			) {
				this.hasJoined = true;
			} else {
				handleRequestError( error, 'Failed to join the channel' );
			}
		}
	}

	/**
	 * Post a new message. Returns the message `ts` (used for threading).
	 */
	async postMessage( text: string ): Promise< string | undefined > {
		try {
			const response = await this.getWeb().chat.postMessage( {
				channel: this.channelId,
				token: this.token,
				text,
			} );
			return response.ts;
		} catch ( error ) {
			handleRequestError( error, 'Failed to post message to Slack' );
			return undefined;
		}
	}

	/**
	 * Update an existing message by its `ts`.
	 */
	async updateMessage( ts: string, text: string ): Promise< void > {
		try {
			await this.getWeb().chat.update( {
				channel: this.channelId,
				token: this.token,
				ts,
				text,
			} );
		} catch ( error ) {
			handleRequestError( error, 'Failed to update Slack message' );
		}
	}

	/**
	 * Post a threaded reply under a parent message.
	 * Returns the reply `ts` (for later updates), or `undefined` on failure.
	 */
	async postReply(
		threadTs: string,
		text: string
	): Promise< string | undefined > {
		try {
			const response = await this.getWeb().chat.postMessage( {
				channel: this.channelId,
				token: this.token,
				thread_ts: threadTs,
				text,
			} );
			return response.ts;
		} catch ( error ) {
			handleRequestError( error, 'Failed to post reply to Slack thread' );
			return undefined;
		}
	}

	/**
	 * Upload a file (screenshot) as a threaded reply.
	 */
	async uploadScreenshot(
		threadTs: string,
		filePath: string,
		filename: string
	): Promise< void > {
		try {
			await this.getWeb().filesUploadV2( {
				filename,
				file: createReadStream( filePath ),
				token: this.token,
				channel_id: this.channelId,
				thread_ts: threadTs,
			} );
		} catch ( error ) {
			handleRequestError( error, 'Failed to upload screenshot to Slack' );
		}
	}
}
