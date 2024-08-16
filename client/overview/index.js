/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, Notice } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';

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
import PaymentActivity from 'wcpay/components/payment-activity';
import Welcome from 'components/welcome';
import { TestModeNotice } from 'components/test-mode-notice';
import InboxNotifications from './inbox-notifications';
import ProgressiveOnboardingEligibilityModal from './modal/progressive-onboarding-eligibility';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import { useDisputes, useGetSettings, useSettings } from 'data';
import SandboxModeSwitchToLiveNotice from 'wcpay/components/sandbox-mode-switch-to-live-notice';
import './style.scss';
import BannerNotice from 'wcpay/components/banner-notice';

const OverviewPageError = () => {
	const queryParams = getQuery();
	const showLoginError = queryParams[ 'wcpay-login-error' ] === '1';
	if ( ! wcpaySettings.errorMessage && ! showLoginError ) {
		return null;
	}
	return (
		<BannerNotice
			className={ showLoginError ? 'wcpay-login-error' : '' }
			status="error"
			icon={ true }
			isDismissible={ false }
		>
			{ wcpaySettings.errorMessage ||
				__(
					'There was a problem redirecting you to the account dashboard. Please try again.',
					'woocommerce-payments'
				) }
		</BannerNotice>
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

	const isOnboardingTestMode = wcpaySettings.onboardingTestMode;
	const { isLoading: settingsIsLoading } = useSettings();
	const [
		isTestDriveSuccessDisplayed,
		setTestDriveSuccessDisplayed,
	] = useState( false );
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

	// We want to show the sandbox success notice only if the account is enabled or complete.
	const isSandboxOnboardedSuccessful =
		queryParams[ 'wcpay-sandbox-success' ] === 'true' &&
		( ( accountStatus.status && accountStatus.status === 'complete' ) ||
			accountStatus.status === 'enabled' );

	const showLoanOfferError = queryParams[ 'wcpay-loan-offer-error' ] === '1';
	const showServerLinkError =
		queryParams[ 'wcpay-server-link-error' ] === '1';
	const showResetAccountError =
		queryParams[ 'wcpay-reset-account-error' ] === '1';
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

	if ( ! isTestDriveSuccessDisplayed && isSandboxOnboardedSuccessful ) {
		dispatch( 'core/notices' ).createSuccessNotice(
			__(
				'Success! You can start using WooPayments in sandbox mode.',
				'woocommerce-payments'
			)
		);

		// Ensure the success message is displayed only once.
		setTestDriveSuccessDisplayed( true );
	}

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
			{ showResetAccountError && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'There was a problem resetting your account. Please wait a few seconds and try again.',
						'woocommerce-payments'
					) }
				</Notice>
			) }
			{ isOnboardingTestMode ? (
				<SandboxModeSwitchToLiveNotice
					from="WCPAY_OVERVIEW"
					source="wcpay-overview-page"
				/>
			) : (
				<TestModeNotice
					currentPage="overview"
					isOnboardingTestMode={ isOnboardingTestMode }
					actions={ [] }
				/>
			) }
			<ErrorBoundary>
				<FRTDiscoverabilityBanner />
			</ErrorBoundary>
			{ showConnectionSuccess && <ConnectionSuccessNotice /> }
			{ ! accountRejected && ! accountUnderReview && (
				<ErrorBoundary>
					<Welcome />

					{ showTaskList && (
						<Card>
							<ErrorBoundary>
								<TaskList
									tasks={ tasks }
									overviewTasksVisibility={
										overviewTasksVisibility
									}
								/>
							</ErrorBoundary>
						</Card>
					) }

					<Card>
						<ErrorBoundary>
							<AccountBalances />
						</ErrorBoundary>
					</Card>

					{
						/* Show Payment Activity widget only when feature flag is set. To be removed before go live */
						isPaymentOverviewWidgetEnabled && (
							<ErrorBoundary>
								<PaymentActivity />
							</ErrorBoundary>
						)
					}

					<DepositsOverview />
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
		</Page>
	);
};

export default OverviewPage;
