/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import TaskList from './task-list';

const OverviewPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.overview } />
			<TaskList />
		</Page>
	);
};

export default OverviewPage;
