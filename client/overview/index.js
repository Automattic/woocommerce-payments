/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, Notice } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import AccountBalances from 'components/account-balances';
import AccountStatus from 'components/account-status';
import ActiveLoanSummary from 'components/active-loan-summary';
import ConnectionSuccessNotice from './connection-sucess-notice';
import DepositsOverview from 'components/deposits-overview';
import ErrorBoundary from 'components/error-boundary';
import FRTDiscoverabilityBanner from 'components/fraud-risk-tools-banner';
import JetpackIdcNotice from 'components/jetpack-idc-notice';
import Page from 'components/page';
import PaymentActivity from 'components/payment-activity';
import Welcome from 'components/welcome';
import { TestModeNotice } from 'components/test-mode-notice';
import InboxNotifications from './inbox-notifications';
import ProgressiveOnboardingEligibilityModal from './modal/progressive-onboarding-eligibility';
import SetupLivePaymentsModal from './modal/setup-live-payments';
import strings from './strings';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import { useDisputes, useGetSettings, useSettings } from 'data';
import './style.scss';

const OverviewPageError = () => {
	const queryParams = getQuery();
	const showLoginError = queryParams[ 'wcpay-login-error' ] === '1';
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
		accountStatus: { progressiveOnboarding },
		accountLoans: { has_active_loan: hasActiveLoan },
		enabledPaymentMethods,
		featureFlags: { isPaymentOverviewWidgetEnabled },
		overviewTasksVisibility,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
	} = wcpaySettings;

	const isDevMode = wcpaySettings.devMode;
	const { isLoading: settingsIsLoading } = useSettings();
	const [ livePaymentsModalVisible, setLivePaymentsModalVisible ] = useState(
		false
	);
	const settings = useGetSettings();

	const { disputes: activeDisputes } = useDisputes( {
		filter: 'awaiting_response',
		per_page: 50,
	} );

	const tasksUnsorted = getTasks( {
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		activeDisputes,
		enabledPaymentMethods,
	} );
	const tasks =
		Array.isArray( tasksUnsorted ) && tasksUnsorted.sort( taskSort );

	const queryParams = getQuery();
	const accountRejected =
		accountStatus.status && accountStatus.status.startsWith( 'rejected' );
	const accountUnderReview = accountStatus.status === 'under_review';

	const showConnectionSuccess =
		queryParams[ 'wcpay-connection-success' ] === '1';

	const showLoanOfferError = queryParams[ 'wcpay-loan-offer-error' ] === '1';
	const showServerLinkError =
		queryParams[ 'wcpay-server-link-error' ] === '1';
	const showProgressiveOnboardingEligibilityModal =
		showConnectionSuccess &&
		progressiveOnboarding.isEnabled &&
		! progressiveOnboarding.isComplete;
	const showTaskList =
		! accountRejected && ! accountUnderReview && tasks.length > 0;

	const activeAccountFees = Object.entries( wcpaySettings.accountFees )
		.map( ( [ key, value ] ) => {
			const isPaymentMethodEnabled =
				! settingsIsLoading &&
				settings.enabled_payment_method_ids.filter(
					( enabledMethod ) => {
						return enabledMethod === key;
					}
				).length > 0;
			if (
				settingsIsLoading ||
				! isPaymentMethodEnabled ||
				value.discount.length === 0
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
			<TestModeNotice
				currentPage="overview"
				isDevMode={ isDevMode }
				actions={
					isDevMode
						? [
								{
									label: strings.notice.actions.setUpPayments,
									onClick: () =>
										setLivePaymentsModalVisible( true ),
								},
								{
									label: strings.notice.actions.learnMore,
									url:
										'https://woo.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/',
									urlTarget: '_blank',
								},
						  ]
						: []
				}
			/>
			<ErrorBoundary>
				<FRTDiscoverabilityBanner />
			</ErrorBoundary>
			{ showConnectionSuccess && <ConnectionSuccessNotice /> }
			{ ! accountRejected && ! accountUnderReview && (
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
						{
							/* Show Payment Activity widget only when feature flag is set. To be removed before go live */
							isPaymentOverviewWidgetEnabled && (
								<Card>
									<ErrorBoundary>
										<PaymentActivity />
									</ErrorBoundary>
								</Card>
							)
						}
						<DepositsOverview />
					</>
				</ErrorBoundary>
			) }
			<ErrorBoundary>
				<AccountStatus
					accountStatus={ accountStatus }
					accountFees={ activeAccountFees }
				/>
			</ErrorBoundary>
			{ hasActiveLoan && (
				<ErrorBoundary>
					<ActiveLoanSummary />
				</ErrorBoundary>
			) }
			{ ! accountRejected && ! accountUnderReview && (
				<ErrorBoundary>
					<InboxNotifications />
				</ErrorBoundary>
			) }
			{ showProgressiveOnboardingEligibilityModal && (
				<ErrorBoundary>
					<ProgressiveOnboardingEligibilityModal />
				</ErrorBoundary>
			) }
			{ livePaymentsModalVisible && (
				<ErrorBoundary>
					<SetupLivePaymentsModal
						closeModal={ () =>
							setLivePaymentsModalVisible( false )
						}
					/>
				</ErrorBoundary>
			) }
		</Page>
	);
};

export default OverviewPage;
