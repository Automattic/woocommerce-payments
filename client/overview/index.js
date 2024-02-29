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
import Page from 'components/page';
import { TestModeNotice } from 'components/test-mode-notice';
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
import ProgressiveOnboardingEligibilityModal from './modal/progressive-onboarding-eligibility';
import JetpackIdcNotice from 'components/jetpack-idc-notice';
import FRTDiscoverabilityBanner from 'components/fraud-risk-tools-banner';
import { useDisputes, useGetSettings, useSettings } from 'wcpay/data';
import strings from './strings';
import './style.scss';
import SetupLivePaymentsModal from './modal/setup-live-payments';
import PaymentActivity from 'wcpay/components/payment-activity';

const PaymentActivityCard = () => {
	return (
		<Card>
			<ErrorBoundary>
				<PaymentActivity />
			</ErrorBoundary>
		</Card>
	);
};

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
		overviewTasksVisibility,
		showUpdateDetailsTask,
		wpcomReconnectUrl,
		enabledPaymentMethods,
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

	const showConnectionSuccess =
		queryParams[ 'wcpay-connection-success' ] === '1';

	const showLoanOfferError = queryParams[ 'wcpay-loan-offer-error' ] === '1';
	const showServerLinkError =
		queryParams[ 'wcpay-server-link-error' ] === '1';
	const showProgressiveOnboardingEligibilityModal =
		showConnectionSuccess &&
		accountStatus.progressiveOnboarding.isEnabled &&
		! accountStatus.progressiveOnboarding.isComplete;
	const showTaskList = ! accountRejected && tasks.length > 0;

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
								<PaymentActivityCard />
							</>
						) : (
							<>
								<Card>
									<Welcome />
									<AccountBalances />
								</Card>
								<PaymentActivityCard />
							</>
						) }

						<DepositsOverview />
					</>
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
