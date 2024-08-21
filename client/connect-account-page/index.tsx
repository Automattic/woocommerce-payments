/** @format **/

/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from 'react';
import { render } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Panel,
	PanelBody,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Loader } from '@woocommerce/onboarding';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';
import Page from 'components/page';
import BannerNotice from 'components/banner-notice';
import Incentive from './incentive';
import InfoNotice from './info-notice-modal';
import OnboardingLocationCheckModal from './modal';
import LogoImg from 'assets/images/woopayments.svg?asset';
import strings from './strings';
import './style.scss';
import InlineNotice from 'components/inline-notice';
import { WooPaymentMethodsLogos } from 'components/payment-method-logos';
import WooPaymentsLogo from 'assets/images/logo.svg?asset';
import { sanitizeHTML } from 'wcpay/utils/sanitize';
import { isInDevMode } from 'wcpay/utils';
import ResetAccountModal from 'wcpay/overview/modal/reset-account';
import { trackAccountReset } from 'wcpay/onboarding/tracking';
import SandboxModeSwitchToLiveNotice from 'wcpay/components/sandbox-mode-switch-to-live-notice';

interface AccountData {
	status: string;
}

const SandboxModeNotice = () => (
	<BannerNotice icon status="warning" isDismissible={ false }>
		{ strings.sandboxModeNotice }
	</BannerNotice>
);

const TestDriveLoader: React.FunctionComponent< {
	progress: number;
} > = ( { progress } ) => (
	<Loader className="connect-account-page__preloader">
		<img src={ WooPaymentsLogo } alt="" />
		<Loader.Layout>
			<Loader.Title>
				{ __(
					'Creating your sandbox account',
					'woocommerce-payments'
				) }
			</Loader.Title>
			<Loader.ProgressBar progress={ progress ?? 0 } />
			<Loader.Sequence interval={ 0 }>
				{ __(
					'In just a few moments, you will be ready to test payments on your store.'
				) }
			</Loader.Sequence>
		</Loader.Layout>
	</Loader>
);

const ConnectAccountPage: React.FC = () => {
	const firstName = wcSettings.admin?.currentUserData?.first_name;
	const incentive = wcpaySettings.connectIncentive;
	const [ modalVisible, setModalVisible ] = useState( false );

	const [ errorMessage, setErrorMessage ] = useState< string >(
		wcpaySettings.errorMessage
	);
	const [ isSubmitted, setSubmitted ] = useState( false );
	const [ isTestDriveModeSubmitted, setTestDriveModeSubmitted ] = useState(
		false
	);
	const [ isTestDriveModeModalShown, setTestDriveModeModalShown ] = useState(
		false
	);
	const [ testDriveLoaderProgress, setTestDriveLoaderProgress ] = useState(
		5
	);

	// Create a reference object.
	const loaderProgressRef = useRef( testDriveLoaderProgress );
	loaderProgressRef.current = testDriveLoaderProgress;

	const {
		connectUrl,
		connect: { availableCountries, country },
		devMode,
		onboardingTestMode,
		isJetpackConnected,
		isAccountConnected,
		isAccountValid,
	} = wcpaySettings;

	const isCountrySupported = !! availableCountries[ country ];

	const urlParams = new URLSearchParams( window.location.search );

	const determineTrackingSource = () => {
		// If we have a source query param in the current request, use that.
		const urlSource = urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' );
		if ( !! urlSource && 'unknown' !== urlSource ) {
			return urlSource;
		}

		// Next, search for a source in the Connect URL as that is determined server-side and it is reliable.
		if ( connectUrl.includes( 'source=' ) ) {
			const url = new URL( connectUrl );
			const source = url.searchParams.get( 'source' );
			if ( !! source && 'unknown' !== source ) {
				return source;
			}
		}
		// Finally, make some guesses based on the 'from' query param.
		// We generally should not reach this step, but it's a fallback with reliable guesses.
		const urlFrom = urlParams.get( 'from' ) || '';
		let sourceGuess = 'wcpay-connect-page';
		switch ( urlFrom ) {
			case 'WCADMIN_PAYMENT_TASK':
				sourceGuess = 'wcadmin-payment-task';
				break;
			case 'WCADMIN_PAYMENT_SETTINGS':
				sourceGuess = 'wcadmin-settings-page';
				break;
			case 'WCADMIN_PAYMENT_INCENTIVE':
				sourceGuess = 'wcadmin-incentive-page';
				break;
		}

		return sourceGuess;
	};

	const determineTrackingFrom = () => {
		return urlParams.get( 'from' )?.replace( /[^\w-]+/g, '' ) || '';
	};

	const trackConnectAccountClicked = ( sandboxMode: boolean ) => {
		recordEvent( 'wcpay_connect_account_clicked', {
			wpcom_connection: isJetpackConnected ? 'Yes' : 'No',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			sandbox_mode: sandboxMode,
			path: 'payments_connect_v2',
			from: determineTrackingFrom(),
			source: determineTrackingSource(),
		} );
	};

	const updateLoaderProgress = ( maxPercent: number, step: number ) => {
		if ( loaderProgressRef.current < maxPercent ) {
			const newProgress = loaderProgressRef.current + step;
			setTestDriveLoaderProgress( newProgress );
		}
	};

	const checkAccountStatus = () => {
		// Fetch account status from the cache.
		apiFetch( {
			path: `/wc/v3/payments/accounts`,
			method: 'GET',
		} ).then( ( account ) => {
			// Simulate the update of the loader progress bar by 4% per check.
			// Limit to a maximum of 15 checks or 30 seconds.
			updateLoaderProgress( 100, 4 );

			// If the account status is not a pending one or progress percentage is above 95,
			// consider our work done and redirect the merchant.
			// Otherwise, schedule another check after 2 seconds.
			if (
				( account &&
					( account as AccountData ).status &&
					! ( account as AccountData ).status.includes(
						'pending'
					) ) ||
				loaderProgressRef.current > 95
			) {
				setTestDriveLoaderProgress( 100 );

				// Redirect to the Connect URL and let it figure it out where to point the merchant.
				window.location.href = addQueryArgs( connectUrl, {
					test_drive: 'true',
					'wcpay-sandbox-success': 'true',
					source: determineTrackingSource(),
					from: 'WCPAY_CONNECT',
				} );
			} else {
				setTimeout( checkAccountStatus, 2000 );
			}
		} );
	};

	const handleSetupTestDriveMode = async () => {
		setTestDriveLoaderProgress( 5 );
		setTestDriveModeSubmitted( true );
		trackConnectAccountClicked( true );

		// Scroll the page to the top to ensure the logo is visible.
		window.scrollTo( {
			top: 0,
		} );

		const customizedConnectUrl = addQueryArgs( connectUrl, {
			test_drive: 'true',
		} );

		const updateProgress = setInterval( updateLoaderProgress, 2500, 40, 5 );

		// If Jetpack is connected, we should proceed with AJAX onboarding.
		// Otherwise, redirect to the Jetpack connect screen.
		if ( wcpaySettings.isJetpackConnected ) {
			setTestDriveModeModalShown( true );
			fetch( customizedConnectUrl, {
				method: 'GET',
				redirect: 'follow',
				credentials: 'same-origin',
				headers: {
					'content-type': 'application/json',
					// Make sure we don't cache the response.
					pragma: 'no-cache',
					'cache-control': 'no-cache',
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					// Please bear in mind that the fetch request will be redirected and the response we will get is from
					// the final URL in the redirect chain.

					if (
						! response?.success ||
						! response?.data?.redirect_to
					) {
						// If we didn't get a redirect_to URL,
						// refresh the page with an error flag to show the error message.
						window.location.href = addQueryArgs(
							window.location.href,
							{
								test_drive_error: 'true',
							}
						);
						return;
					}

					clearInterval( updateProgress );
					setTestDriveLoaderProgress( 40 );

					// Check the url for the `wcpay-connection-success` parameter, indicating a successful connection.
					const responseUrlParams = new URLSearchParams(
						response.data.redirect_to
					);
					const connectionSuccess =
						responseUrlParams.get( 'wcpay-connection-success' ) ||
						'';

					// The account has been successfully onboarded.
					if ( !! connectionSuccess ) {
						// Start checking the account status in a loop.
						checkAccountStatus();
					} else {
						// Redirect to the response URL, but attach our test drive flags.
						// This URL is generally a Connect page URL.
						window.location.href = addQueryArgs(
							response.data.redirect_to,
							{
								test_drive: 'true',
								test_drive_error: 'true',
							}
						);
					}
				} )
				.catch( () => {
					// If the fetch request fails, refresh the page with an error flag to show the error message.
					window.location.href = addQueryArgs( window.location.href, {
						test_drive_error: 'true',
					} );
				} );
		} else {
			// Redirect to the connect URL to set up the Jetpack connection.
			window.location.href = addQueryArgs( customizedConnectUrl, {
				auto_start_test_drive_onboarding: 'true', // This is a flag to start the onboarding automatically.
			} );
		}
	};

	const autoStartTestDriveOnboarding = () => {
		// If Jetpack is connected and the parameter is present in the URL,
		// we should start onboarding Test Drive account automatically.
		if (
			wcpaySettings.isJetpackConnected &&
			urlParams.get( 'auto_start_test_drive_onboarding' )
		) {
			handleSetupTestDriveMode();
		}
	};

	useEffect( () => {
		recordEvent( 'page_view', {
			path: 'payments_connect_v2',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			from: determineTrackingFrom(),
			source: determineTrackingSource(),
		} );

		// Maybe auto-start the test drive onboarding.
		autoStartTestDriveOnboarding();

		// We only want to run this once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const handleLocationCheck = () => {
		// Reset the 'Set up' button state if merchant decided to stop
		const handleModalDeclined = () => {
			setSubmitted( false );
		};
		// Redirect the merchant if merchant decided to continue
		const handleModalConfirmed = () => {
			window.location.href = addQueryArgs( connectUrl, {
				source: determineTrackingSource(),
				from: 'WCPAY_CONNECT',
			} );
		};

		// Populate translated list of supported countries we want to render in the modal window.
		const countries = Object.values( availableCountries )
			.sort()
			.map( ( countryName ) => {
				return { title: countryName };
			} );

		const container = document.createElement( 'div' );
		container.id = 'wcpay-onboarding-location-check-container';
		render(
			<OnboardingLocationCheckModal
				countries={ countries }
				onDeclined={ handleModalDeclined }
				onConfirmed={ handleModalConfirmed }
			/>,
			container
		);
		document.body.appendChild( container );
	};

	const handleSetup = async () => {
		setSubmitted( true );

		trackConnectAccountClicked( false );

		// If there is an incentive available, request promo activation before redirecting.
		// Display an error message if the request fails.
		if ( incentive ) {
			try {
				const activatePromoRequest = await apiFetch< {
					success: boolean;
				} >( {
					path: `/wc-analytics/admin/notes/experimental-activate-promo/${ incentive.id }`,
					method: 'POST',
				} );
				if ( ! activatePromoRequest?.success ) throw new Error();
			} catch ( _ ) {
				setErrorMessage( strings.incentive.error );
			}
		}

		// Inform the merchant if country specified in business address is not yet supported, but allow to proceed.
		if ( ! isCountrySupported ) {
			return handleLocationCheck();
		}

		window.location.href = addQueryArgs( connectUrl, {
			source: determineTrackingSource(),
			from: 'WCPAY_CONNECT',
		} );
	};

	const handleReset = () => {
		trackAccountReset();

		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			'wcpay-reset-account': 'true',
			from: 'WCPAY_CONNECT',
			source: determineTrackingSource(),
		} );
	};

	let isAccountSetupSessionError = false;
	// Determine if we have the account session error message since we want to customize the UX a little bit.
	if ( errorMessage && errorMessage.includes( 'account setup session' ) ) {
		isAccountSetupSessionError = true;
	}

	const isAccountTestDriveError =
		'true' === urlParams.get( 'test_drive_error' );
	if ( ! errorMessage && isAccountTestDriveError ) {
		// If there isn't an error message from elsewhere, but we have a test drive error,
		// show the test drive error message.
		setErrorMessage(
			__(
				'An error occurred while setting up your sandbox account. Please try again!',
				'woocommerce-payments'
			)
		);
	}

	let ctaLabel = strings.button.jetpack_not_connected;
	if ( isJetpackConnected ) {
		ctaLabel = strings.button.account_not_connected;
		// If we have the account setup session error, best not to push too much with the CTA copy.
		if (
			! isAccountSetupSessionError &&
			isAccountConnected &&
			! isAccountValid
		) {
			ctaLabel = strings.button.account_invalid;
		}
	}

	// If there is no error message from elsewhere, but we have:
	// - a broken Jetpack connection and a connected account;
	// - or working Jetpack connection and a connected but invalid account.
	// show a generic error message.
	if (
		! errorMessage &&
		( ( ! isJetpackConnected && isAccountConnected ) ||
			( isJetpackConnected && isAccountConnected && ! isAccountValid ) )
	) {
		setErrorMessage( strings.setupErrorNotice );
	}

	return (
		<Page isNarrow className="connect-account-page">
			{ errorMessage && (
				<BannerNotice
					status="error"
					icon={ true }
					isDismissible={ false }
				>
					<div
						// eslint-disable-next-line react/no-danger
						dangerouslySetInnerHTML={ sanitizeHTML( errorMessage ) }
					></div>
				</BannerNotice>
			) }
			{ wcpaySettings.onBoardingDisabled ? (
				<Card>
					<CardBody>{ strings.onboardingDisabled }</CardBody>
				</Card>
			) : (
				<>
					{ ! isCountrySupported && (
						<BannerNotice status="error" isDismissible={ false }>
							{ strings.nonSupportedCountry }
						</BannerNotice>
					) }
					{
						// Show general sandbox notice when no account is connected but sandbox mode is active.
						! isAccountConnected && devMode ? (
							<SandboxModeNotice />
						) : (
							// If we already have a sandbox account connected (but in an invalid state) and
							// a working Jetpack connection (to be able to delete the current account)
							// show the switch to live sandbox notice.
							isAccountConnected &&
							! isAccountValid &&
							onboardingTestMode &&
							isJetpackConnected && (
								<SandboxModeSwitchToLiveNotice
									from="WCPAY_CONNECT"
									source="wcpay-connect-page"
								/>
							)
						)
					}
					<Card>
						<div className="connect-account-page__heading">
							<img src={ LogoImg } alt="logo" />
							<h2>{ strings.heading( firstName ) }</h2>
						</div>
						<div className="connect-account-page__content">
							<InfoNotice />
						</div>
						<div className="connect-account-page__payment-methods">
							<WooPaymentMethodsLogos maxElements={ 10 } />
							<div className="connect-account-page__payment-methods__description">
								<div>
									<p>
										{
											strings.paymentMethods.deposits
												.title
										}
									</p>
									<span>
										{
											strings.paymentMethods.deposits
												.value
										}
									</span>
								</div>
								<div className="connect-account-page__payment-methods__description__divider"></div>
								<div>
									<p>
										{ strings.paymentMethods.capture.title }
									</p>
									<span>
										{ strings.paymentMethods.capture.value }
									</span>
								</div>
								<div className="connect-account-page__payment-methods__description__divider"></div>
								<div>
									<p>
										{
											strings.paymentMethods.recurring
												.title
										}
									</p>
									<span>
										{
											strings.paymentMethods.recurring
												.value
										}
									</span>
								</div>
							</div>
						</div>
						<div className="connect-account-page__buttons">
							<Button
								variant="primary"
								isBusy={ isSubmitted }
								disabled={
									isSubmitted || isAccountSetupSessionError
								}
								onClick={ handleSetup }
							>
								{ ctaLabel }
							</Button>
							{
								// Only show the reset button if an account is connected and didn't complete KYC, or if we are in dev mode.
								isAccountConnected &&
									( ! wcpaySettings.accountStatus
										.detailsSubmitted ||
										isInDevMode() ) && (
										<Button
											variant={ 'tertiary' }
											onClick={ () =>
												setModalVisible( true )
											}
										>
											{ strings.button.reset }
										</Button>
									)
							}
						</div>
					</Card>
					{
						// Only show the incentive if an account is NOT connected.
						! isAccountConnected && incentive && (
							<Incentive { ...incentive } />
						)
					}
					{
						// Only show the sandbox mode panel if an account is NOT connected.
						! isAccountConnected && (
							<Panel className="connect-account-page__sandbox-mode-panel">
								<PanelBody
									title={ strings.sandboxMode.title }
									initialOpen={ false }
								>
									<InlineNotice
										icon
										status="info"
										isDismissible={ false }
									>
										{ strings.sandboxMode.description }
									</InlineNotice>
									<Button
										variant="secondary"
										isBusy={ isTestDriveModeSubmitted }
										disabled={ isTestDriveModeSubmitted }
										onClick={ handleSetupTestDriveMode }
									>
										{ strings.button.sandbox }
									</Button>
								</PanelBody>
							</Panel>
						)
					}
					<ResetAccountModal
						isVisible={ modalVisible }
						onDismiss={ () => setModalVisible( false ) }
						onSubmit={ handleReset }
					/>
				</>
			) }
			{ isTestDriveModeModalShown && (
				<TestDriveLoader progress={ testDriveLoaderProgress } />
			) }
		</Page>
	);
};

export default ConnectAccountPage;
