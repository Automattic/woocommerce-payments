/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Card, Notice } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';
import Welcome from 'components/welcome';
import AccountBalances from 'components/account-balances';
import DepositsOverview from 'components/deposits-overview';
import ActiveLoanSummary from 'components/active-loan-summary';
import ErrorBoundary from 'components/error-boundary';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import InboxNotifications from './inbox-notifications';
import ConnectionSuccessNotice from './connection-sucess-notice';
import SetupRealPayments from './setup-real-payments';
import ProgressiveOnboardingEligibilityModal from './modal/progressive-onboarding-eligibility';
import JetpackIdcNotice from 'components/jetpack-idc-notice';
import FRTDiscoverabilityBanner from 'components/fraud-risk-tools-banner';
import { useSettings } from 'wcpay/data';
import './style.scss';

const OverviewPageError = () => {
	const queryParams = getQuery();
	const showLoginError = '1' === queryParams[ 'wcpay-login-error' ];
	if ( ! wcpaySettings.errorMessage && ! showLoginError ) {
		return null;
	}
	return (
		<Notice
			status="error"
			isDismissible={ false }
			className="wcpay-login-error"
		>
			{ wcpaySettings.errorMessage ||
				__(
					'There was a problem redirecting you to the account dashboard. Please try again.',
					'woocommerce-payments'
				) }
		</Notice>
	);
};

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
	const accountRejected =
		accountStatus.status && accountStatus.status.startsWith( 'rejected' );

	const showConnectionSuccess =
		'1' === queryParams[ 'wcpay-connection-success' ];

	const showLoanOfferError = '1' === queryParams[ 'wcpay-loan-offer-error' ];
	const showServerLinkError =
		'1' === queryParams[ 'wcpay-server-link-error' ];
	const showProgressiveOnboardingEligibilityModal =
		showConnectionSuccess &&
		accountStatus.progressiveOnboarding.isEnabled &&
		! accountStatus.progressiveOnboarding.isComplete &&
		'pending_verification' !== accountStatus.status;
	const showTaskList =
		!! accountOverviewTaskList && ! accountRejected && 0 < tasks.length;

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
			<OverviewPageError />

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

			<ErrorBoundary>
				<FRTDiscoverabilityBanner />
			</ErrorBoundary>

			{ showConnectionSuccess && <ConnectionSuccessNotice /> }

			{ ! accountRejected && (
				<ErrorBoundary>
					<>
						{ showTaskList ? (
							<>
								<Card>
									<Welcome />
									<ErrorBoundary>
										<TaskList
											tasks={ tasks }
											overviewTasksVisibility={
												overviewTasksVisibility
											}
										/>
									</ErrorBoundary>
								</Card>
								<Card>
									<AccountBalances />
								</Card>
							</>
						) : (
							<Card>
								<Welcome />
								<AccountBalances />
							</Card>
						) }

						<DepositsOverview />
					</>
				</ErrorBoundary>
			) }

			{ wcpaySettings.onboardingTestMode && (
				<ErrorBoundary>
					<SetupRealPayments />
				</ErrorBoundary>
			) }

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

			{ showProgressiveOnboardingEligibilityModal && (
				<ErrorBoundary>
					<ProgressiveOnboardingEligibilityModal />
				</ErrorBoundary>
			) }
		</Page>
	);
};

export default OverviewPage;
