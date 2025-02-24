/**
 * External dependencies
 */
import type { Reporter, TestCase, TestResult } from '@playwright/test/reporter';

/**
 * Internal dependencies
 */
import * as Slack from '../utils/slack';

class SlackReporter implements Reporter {
	onTestEnd( test: TestCase, result: TestResult ) {
		// If the test has already failed, we don't want to send a duplicate message.
		if ( result.retry !== 0 ) {
			return;
		}

		if ( test.outcome() === 'unexpected' ) {
			Slack.sendFailedTestMessageToSlack( test.title );

			const screenshots = result.attachments.filter(
				( { name, path } ) => name === 'screenshot' && path
			);

			if ( screenshots.length > 0 ) {
				const [ screenshot ] = screenshots;
				Slack.sendFailedTestScreenshotToSlack( screenshot.path );
			}
		}
	}
}

export default SlackReporter;
