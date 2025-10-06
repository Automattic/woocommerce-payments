/** @format **/

/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { getQuery } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import interpolateComponents from '@automattic/interpolate-components';
import { Card, Notice, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import AccountBalances from 'components/account-balances';
import AccountStatus from 'components/account-status';
import AccountDetails from 'components/account-details';
import ActiveLoanSummary from 'components/active-loan-summary';
import ConnectionSuccessModal from './modal/connection-success';
import DepositsOverview from 'components/deposits-overview';
import ErrorBoundary from 'components/error-boundary';
import JetpackIdcNotice from 'components/jetpack-idc-notice';
import Page from 'components/page';
import Welcome from 'components/welcome';
import { TestModeNotice } from 'components/test-mode-notice';
import InboxNotifications from './inbox-notifications';
import TaskList from './task-list';
import { getTasks, taskSort } from './task-list/tasks';
import { useDisputes, useGetSettings, useSettings } from 'data';
import SandboxModeSwitchToLiveNotice from 'wcpay/components/sandbox-mode-switch-to-live-notice';
import './style.scss';
import BannerNotice from 'wcpay/components/banner-notice';
import { MaybeShowMerchantFeedbackPrompt } from 'wcpay/merchant-feedback-prompt';
import { recordEvent } from 'wcpay/tracks';
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { getAdminUrl, isInTestModeOnboarding } from 'wcpay/utils';
import { EmbeddedConnectNotificationBanner } from 'wcpay/embedded-components';

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
		accountLoans: { has_active_loan: hasActiveLoan },
		overviewTasksVisibility,
		wpcomReconnectUrl,
		featureFlags: { isAccountDetailsEnabled },
		accountDetails,
	} = wcpaySettings;

	// Don't show the update details and verify business tasks by default due to embedded component.
	const [ showUpdateDetailsTask, setShowUpdateDetailsTask ] = useState(
		false
	);

	const [
		stripeNotificationsBannerErrorMessage,
		setStripeNotificationsBannerErrorMessage,
	] = useState( '' );
	const [
		stripeNotificationsBannerErrorType,
		setStripeNotificationsBannerErrorType,
	] = useState( '' );
	const [
		notificationsBannerMessage,
		setNotificationsBannerMessage,
	] = React.useState( '' );
	const [ stripeComponentLoading, setStripeComponentLoading ] = useState(
		true
	);
	// Variable to memoize the count of Stripe notifications.
	const [
		stripeNotificationsCountToAddressMemo,
		setStripeNotificationsCountToAddressMemo,
	] = useState( 0 );

	const isTestModeOnboarding = isInTestModeOnboarding();
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
	} );
	const tasks =
		Array.isArray( tasksUnsorted ) && tasksUnsorted.sort( taskSort );

	const queryParams = getQuery();
	const accountRejected =
		accountStatus.status && accountStatus.status.startsWith( 'rejected' );
	const accountUnderReview = accountStatus.status === 'under_review';
	const paymentsEnabled = accountStatus.paymentsEnabled;
	const depositsEnabled = accountStatus.deposits?.status === 'enabled';

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
	const showTaskList =
		! accountRejected && ! accountUnderReview && tasks.length > 0;
	const showConnectionSuccessModal =
		showConnectionSuccess &&
		! isTestModeOnboarding &&
		paymentsEnabled &&
		depositsEnabled;

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
				'Success! You can start using WooPayments in test mode.',
				'woocommerce-payments'
			)
		);

		// Ensure the success message is displayed only once.
		setTestDriveSuccessDisplayed( true );
	}

	// Show old tasks if the embedded component fails to load.
	useEffect( () => {
		if ( stripeNotificationsBannerErrorMessage ) {
			setShowUpdateDetailsTask( true );
			setStripeComponentLoading( false );
		}
	}, [ stripeNotificationsBannerErrorMessage ] );

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Configure custom banner behaviour so the banner isn't shown when there are no action items.
	 * We'll use notificationBannerMessage for that.
	 * See https://docs.stripe.com/connect/supported-embedded-components/notification-banner#configure-custom-banner-behavior
	 */
	const handleNotificationsChange = ( response ) => {
		if ( response.actionRequired > 0 ) {
			// eslint-disable-next-line max-len
			// Do something related to required actions, such as adding margins to the banner container or tracking the current number of notifications.
			setNotificationsBannerMessage(
				'You must resolve the notifications on this page before proceeding.'
			);
		} else if ( response.total > 0 ) {
			// Do something related to notifications that don't require action.
			setNotificationsBannerMessage( 'The items below are in review.' );
		} else {
			// This is the case where we addressed everything and previously had some notifications to address.
			// We recommend the merchant to reload the page in this case.
			if ( stripeNotificationsCountToAddressMemo > 0 ) {
				dispatch( 'core/notices' ).createSuccessNotice(
					__(
						'Updates take a moment to appear. Please refresh the page in a minute.',
						'woocommerce-payments'
					),
					{
						actions: [
							{
								label: __( 'Refresh', 'woocommerce-payments' ),
								url: getAdminUrl( {
									page: 'wc-admin',
									path: '/payments/overview',
								} ),
							},
						],
						explicitDismiss: true,
					}
				);
				// No need to add extra params, we are interested in the total amount of actions here.
				recordEvent(
					'wcpay_overview_stripe_notifications_banner_action_completed'
				);
			}
			setNotificationsBannerMessage( '' );
		}
		if ( response.actionRequired > 0 || response.total > 0 ) {
			// Record the event indicating user got the notifications banner with some actionRequired or total items.
			recordEvent( 'wcpay_overview_stripe_notifications_banner_update', {
				action_required_count: response.actionRequired,
				total_count: response.total,
			} );
			// Memoize the notifications count to be able to compare it with the fresh count when this function is called one more time.
			setStripeNotificationsCountToAddressMemo( response.total );
		}
		// If the component inits successfully, this function is always called.
		// It's safe to set the loading false here rather than onLoaderStart, because it happens too early and the UX is not smooth.
		setStripeComponentLoading( false );
	};

	return (
		<Page isNarrow className="wcpay-overview">
			<MaybeShowMerchantFeedbackPrompt />
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
			{ isTestModeOnboarding ? (
				<SandboxModeSwitchToLiveNotice
					from="WCPAY_OVERVIEW"
					source="wcpay-overview-page"
				/>
			) : (
				<TestModeNotice
					currentPage="overview"
					isTestModeOnboarding={ isTestModeOnboarding }
					actions={ [] }
				/>
			) }
			{ stripeNotificationsBannerErrorMessage &&
				stripeNotificationsBannerErrorType ===
					'invalid_request_error' && (
					<BannerNotice
						status="warning"
						icon={ true }
						isDismissible={ false }
					>
						{ interpolateComponents( {
							mixedString: sprintf(
								__(
									// eslint-disable-next-line max-len
									'Some account related notifications require HTTPS and cannot be displayed. View them on our financial partner’s website. {{seeDetailsLink}}See details{{/seeDetailsLink}}',
									'woocommerce-payments'
								)
							),
							components: {
								seeDetailsLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<ExternalLink
										href={
											'https://woocommerce.com/document/woopayments/startup-guide/#requirements'
										}
									/>
								),
							},
						} ) }
					</BannerNotice>
				) }
			{ ! accountRejected && ! accountUnderReview && (
				<ErrorBoundary>
					<Welcome />

					{ stripeComponentLoading &&
						accountStatus.status !== 'complete' && (
							<Card>
								<div className="stripe-notifications-banner-loader">
									<StripeSpinner />
								</div>
							</Card>
						) }
					<div
						className="stripe-notifications-banner-wrapper"
						style={ {
							display: notificationsBannerMessage
								? 'block'
								: 'none',
						} }
					>
						<ErrorBoundary>
							<EmbeddedConnectNotificationBanner
								onLoadError={ ( loadError ) => {
									setStripeNotificationsBannerErrorMessage(
										loadError.error.message ||
											'Unknown error'
									);
									setStripeNotificationsBannerErrorType(
										loadError.error.type
									);
									setStripeComponentLoading( false );
								} }
								onNotificationsChange={
									handleNotificationsChange
								}
							/>
						</ErrorBoundary>
					</div>

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

					<DepositsOverview />
				</ErrorBoundary>
			) }
			<ErrorBoundary>
				{ isAccountDetailsEnabled && accountDetails ? (
					<AccountDetails
						accountDetails={ accountDetails }
						accountFees={ activeAccountFees }
						accountLink={ accountStatus.accountLink }
					/>
				) : (
					<AccountStatus
						accountStatus={ accountStatus }
						accountFees={ activeAccountFees }
					/>
				) }
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
			{ showConnectionSuccessModal && (
				<ErrorBoundary>
					<ConnectionSuccessModal />
				</ErrorBoundary>
			) }
		</Page>
	);
};

export default OverviewPage;
