/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';
import TaskList from './task-list';
import { getTasks } from './task-list/tasks';
import './style.scss';
import DepositsInformation from 'components/deposits-information';

const OverviewPage = () => {
	const { accountStatus, showUpdateDetailsTask } = wcpaySettings;
	const tasks = getTasks( { accountStatus, showUpdateDetailsTask } );
	return (
		<Page className="overview">
			<TestModeNotice topic={ topics.overview } />
			<DepositsInformation />
			<AccountStatus
				accountStatus={ wcpaySettings.accountStatus }
				accountFees={ wcpaySettings.accountFees }
			/>
			{ 0 < tasks.length && <TaskList tasks={ tasks } /> }
		</Page>
	);
};

export default OverviewPage;
