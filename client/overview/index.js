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

import './style.scss';

const OverviewPage = () => {
	const {
		accountStatus,
		showUpdateDetailsTask,
		featureFlags: { accountOverviewTaskList },
	} = wcpaySettings;

	const tasks = getTasks( { accountStatus, showUpdateDetailsTask } );
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
		</Page>
	);
};

export default OverviewPage;
