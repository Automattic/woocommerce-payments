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
import { getTasks } from './task-list/tasks';
import './style.scss';

const { accountStatus, showUpdateDetailsTask } = wcpaySettings;

const OverviewPage = () => {
	return (
		<Page className="overview">
			<TestModeNotice topic={ topics.overview } />
			<TaskList
				tasks={ getTasks( { accountStatus, showUpdateDetailsTask } ) }
			/>
		</Page>
	);
};

export default OverviewPage;
