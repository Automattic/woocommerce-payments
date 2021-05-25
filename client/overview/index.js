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
import DepositsInformation from 'components/deposits-information';
import TaskList from './task-list';
import { getTasks } from './task-list/tasks';
import './style.scss';

const OverviewPage = () => {
	const {
		accountStatus,
		showUpdateDetailsTask,
		featureFlags: { accountOverviewTaskList },
	} = wcpaySettings;

	const tasks = getTasks( { accountStatus, showUpdateDetailsTask } );
	return (
		<Page className="wcpay-overview">
			<TestModeNotice topic={ topics.overview } />
			<DepositsInformation />
			<AccountStatus
				accountStatus={ wcpaySettings.accountStatus }
				accountFees={ wcpaySettings.accountFees }
			/>
			{ !! accountOverviewTaskList && 0 < tasks.length && (
				<TaskList tasks={ tasks } />
			) }
		</Page>
	);
};

export default OverviewPage;
