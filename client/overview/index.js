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
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';
import DepositsInformation from 'components/deposits-information';
import TaskList from './task-list';
import { getTasks } from './task-list/tasks';
import InboxNotifications from './inbox-notifications';
import { useDisputes } from 'data';

import './style.scss';

const OverviewPage = () => {
	const {
		accountStatus,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		featureFlags: { accountOverviewTaskList },
	} = wcpaySettings;
	const { disputes } = useDisputes( getQuery() );

	const tasks = getTasks( {
		accountStatus,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		disputes,
	} );
	const queryParams = getQuery();

	const showKycSuccessNotice =
		'1' === queryParams[ 'wcpay-connection-success' ];

	return (
		<Page isNarrow className="wcpay-overview">
			{ showKycSuccessNotice && (
				<Notice status="success" isDismissible={ false }>
					{ __(
						"Thanks for verifying your business details. You're ready to start taking payments!",
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
			{ !! accountOverviewTaskList && 0 < tasks.length && (
				<TaskList tasks={ tasks } />
			) }
			<InboxNotifications />
		</Page>
	);
};

export default OverviewPage;
