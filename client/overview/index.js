/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';

const OverviewPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.overview } />
		</Page>
	);
};

export default OverviewPage;
