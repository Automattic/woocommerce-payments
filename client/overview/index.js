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
import { tasks } from './task-list/tasks';
import './style.scss';

const OverviewPage = () => {
	return (
		<Page className="overview">
			<TestModeNotice topic={ topics.overview } />
			<TaskList tasks={ tasks } />
		</Page>
	);
};

export default OverviewPage;
