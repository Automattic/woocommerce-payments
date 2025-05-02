/**
 * External dependencies
 */
import type { Reporter, TestCase, TestResult } from '@playwright/test/reporter';

/**
 * Internal dependencies
 */
import * as Slack from '../utils/slack';

function slugifyForFileName( input: string ): string {
	return input
		.toLowerCase()
		.replace( /[^\w\d-]+/g, '-' ) // Replace non-alphanumeric chars with dashes
		.replace( /-+/g, '-' ) // Collapse multiple dashes
		.replace( /^-|-$/g, '' ); // Trim leading/trailing dashes
}

class SlackReporter implements Reporter {
	onTestEnd( test: TestCase, result: TestResult ) {
		// If the test has already failed, we don't want to send a duplicate message.
		if ( result.retry !== 0 ) {
			return;
		}

		if ( test.outcome() !== 'unexpected' ) {
			return;
		}

		Slack.sendFailedTestMessageToSlack( test.titlePath().join( ' › ' ) );

		const screenshots = result.attachments.filter(
			( { name, path } ) => name === 'screenshot' && path
		);
		if ( screenshots.length > 0 ) {
			const [ screenshot ] = screenshots;
			if ( ! screenshot.path ) {
				return;
			}

			Slack.sendFailedTestScreenshotToSlack(
				screenshot.path,
				slugifyForFileName( test.title )
			);
		}
	}
}

export default SlackReporter;
