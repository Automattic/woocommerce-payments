/** @format **/

/**
 * External dependencies
 */

import { Button, Notice } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';
import ActiveLoanSummary from 'components/active-loan-summary';
import DepositsInformation from 'components/deposits-information';
import ErrorBoundary from 'components/error-boundary';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import InboxNotifications from './inbox-notifications';
import JetpackIdcNotice from 'components/jetpack-idc-notice';

import './style.scss';
import { useSettings } from 'wcpay/data';

const OverviewPage = () => {
	const {
		accountStatus,
		overviewTasksVisibility,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		featureFlags: { accountOverviewTaskList },
	} = wcpaySettings;
	const numDisputesNeedingResponse =
		parseInt( wcpaySettings.numDisputesNeedingResponse, 10 ) || 0;
	const { isLoading: settingsIsLoading, settings } = useSettings();

	const tasksUnsorted = getTasks( {
		accountStatus,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		isAccountOverviewTasksEnabled: Boolean( accountOverviewTaskList ),
		numDisputesNeedingResponse,
	} );
	const tasks =
		Array.isArray( tasksUnsorted ) && tasksUnsorted.sort( taskSort );
	const queryParams = getQuery();

	const showKycSuccessNotice =
		'1' === queryParams[ 'wcpay-connection-success' ];

	const showLoginError = '1' === queryParams[ 'wcpay-login-error' ];
	const showLoanOfferError = '1' === queryParams[ 'wcpay-loan-offer-error' ];
	const showServerLinkError =
		'1' === queryParams[ 'wcpay-server-link-error' ];
	const accountRejected =
		accountStatus.status && accountStatus.status.startsWith( 'rejected' );

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

	const handleSubmit = () => {
		const url = addQueryArgs( wcpaySettings.connectUrl, {
			progressive_complete: true,
		} );

		window.location = url;
	};

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

			<JetpackIdcNotice />

			{ showLoanOfferError && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'There was a problem redirecting you to the loan offer. Please check that it is not expired and try again.',
						'woocommerce-payments'
					) }
				</Notice>
			) }

			{ showServerLinkError && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'There was a problem redirecting you to the requested link. Please check that it is valid and try again.',
						'woocommerce-payments'
					) }
				</Notice>
			) }

			<TestModeNotice topic={ topics.overview } />

			{ ! accountRejected && (
				<ErrorBoundary>
					<DepositsInformation />
				</ErrorBoundary>
			) }

			{ !! accountOverviewTaskList &&
				0 < tasks.length &&
				! accountRejected && (
					<ErrorBoundary>
						<TaskList
							tasks={ tasks }
							overviewTasksVisibility={ overviewTasksVisibility }
						/>
					</ErrorBoundary>
				) }

			<ErrorBoundary>
				<Button isPrimary onClick={ handleSubmit }>
					Set up deposits
				</Button>
			</ErrorBoundary>

			<ErrorBoundary>
				<AccountStatus
					accountStatus={ wcpaySettings.accountStatus }
					accountFees={ activeAccountFees }
				/>
			</ErrorBoundary>

			{ wcpaySettings.accountLoans.has_active_loan && (
				<ErrorBoundary>
					<ActiveLoanSummary />
				</ErrorBoundary>
			) }

			{ ! accountRejected && (
				<ErrorBoundary>
					<InboxNotifications />
				</ErrorBoundary>
			) }
		</Page>
	);
};

export default OverviewPage;
