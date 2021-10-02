/** @format **/

/**
 * External dependencies
 */

import { Notice } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';
import DepositsInformation from 'components/deposits-information';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import InboxNotifications from './inbox-notifications';
import { useDisputes } from 'data';

import './style.scss';

const OverviewPage = () => {
	const {
		accountStatus,
		overviewTasksVisibility,
		showUpdateDetailsTask,
		additionalMethodsSetup,
		multiCurrencySetup,
		wpcomReconnectUrl,
		featureFlags: { accountOverviewTaskList },
		needsHttpsSetup,
	} = wcpaySettings;
	const { disputes, isLoading } = useDisputes( getQuery() );

	const tasksUnsorted = getTasks( {
		accountStatus,
		showUpdateDetailsTask,
		additionalMethodsSetup,
		multiCurrencySetup,
		wpcomReconnectUrl,
		needsHttpsSetup,
		disputes,
	} );
	const tasks =
		Array.isArray( tasksUnsorted ) && tasksUnsorted.sort( taskSort );
	const queryParams = getQuery();

	const showKycSuccessNotice =
		'1' === queryParams[ 'wcpay-connection-success' ];

	const showLoginError = '1' === queryParams[ 'wcpay-login-error' ];

	return (
		<Page isNarrow className="wcpay-overview">
			<ErrorBoundary>
				{ showKycSuccessNotice && (
					<Notice
						status="success"
						isDismissible={ false }
						className="wcpay-connection-success"
					>
						{ __(
							"Thanks for verifying your business details. You're ready to start taking payments!",
							'woocommerce-payments'
						) }
					</Notice>
				) }

				{ showLoginError && (
					<Notice
						status="error"
						isDismissible={ false }
						className="wcpay-login-error"
					>
						{ __(
							'There was a problem redirecting you to the account dashboard. Please try again.',
							'woocommerce-payments'
						) }
					</Notice>
				) }

				<TestModeNotice topic={ topics.overview } />
				<DepositsInformation />
				<AccountStatus
					accountStatus={ wcpaySettings.accountStatus }
					accountFees={ wcpaySettings.accountFees }
				/>
				{ !! accountOverviewTaskList &&
					0 < tasks.length &&
					! isLoading && (
						<TaskList
							tasks={ tasks }
							overviewTasksVisibility={ overviewTasksVisibility }
						/>
					) }
				<InboxNotifications />
			</ErrorBoundary>
		</Page>
	);
};

export default OverviewPage;
