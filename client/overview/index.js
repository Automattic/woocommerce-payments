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
import ErrorBoundary from 'components/error-boundary';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import InboxNotifications from './inbox-notifications';
import { useDisputes } from 'data';

import './style.scss';
import { useSettings } from 'wcpay/data';

const OverviewPage = () => {
	const {
		accountStatus,
		overviewTasksVisibility,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		featureFlags: { accountOverviewTaskList },
		needsHttpsSetup,
	} = wcpaySettings;
	const { disputes, isLoading } = useDisputes( getQuery() );
	const { isLoading: settingsIsLoading, settings } = useSettings();

	const tasksUnsorted = getTasks( {
		accountStatus,
		showUpdateDetailsTask,
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

	const activeAccountFees = Object.entries( wcpaySettings.accountFees )
		.map( ( [ key, value ] ) => {
			const isPaymentMethodEnabled =
				! settingsIsLoading &&
				0 <
					settings.enabled_payment_method_ids.filter(
						( enabledMethod ) => {
							return enabledMethod === key;
						}
					).length;
			if (
				settingsIsLoading ||
				! isPaymentMethodEnabled ||
				0 === value.discount.length
			) {
				return null;
			}
			return { payment_method: key, fee: value };
		} )
		.filter( ( e ) => e && e.fee !== undefined );

	return (
		<Page isNarrow className="wcpay-overview">
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

			<ErrorBoundary>
				<DepositsInformation />
			</ErrorBoundary>

			<ErrorBoundary>
				<AccountStatus
					accountStatus={ wcpaySettings.accountStatus }
					accountFees={ activeAccountFees }
				/>
			</ErrorBoundary>
			{ !! accountOverviewTaskList && 0 < tasks.length && ! isLoading && (
				<ErrorBoundary>
					<TaskList
						tasks={ tasks }
						overviewTasksVisibility={ overviewTasksVisibility }
					/>
				</ErrorBoundary>
			) }
			<ErrorBoundary>
				<InboxNotifications />
			</ErrorBoundary>
		</Page>
	);
};

export default OverviewPage;
