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
	Notice,
	Panel,
	PanelBody,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Loader } from '@woocommerce/onboarding';

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
import { __ } from '@wordpress/i18n';

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
	const isNewFlowEnabled =
		wcpaySettings.progressiveOnboarding?.isNewFlowEnabled;

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
		overviewUrl,
		connect: { availableCountries, country },
		devMode,
	} = wcpaySettings;

	const isCountrySupported = !! availableCountries[ country ];

	const determineTrackingSource = () => {
		const urlParams = new URLSearchParams( window.location.search );
		const from = urlParams.get( 'from' ) || '';

		// Determine where the user came from.
		let source = 'wcadmin';
		switch ( from ) {
			case 'WCADMIN_PAYMENT_TASK':
				source = 'wcadmin-payment-task';
				break;
			case 'WCADMIN_PAYMENT_SETTINGS':
				source = 'wcadmin-settings-page';
				break;
		}

		return source;
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

			// If the account status is complete or progress percentage is above 95, redirect to the overview page.
			// Otherwise, schedule another check after 2 seconds.
			if (
				( account as AccountData ).status === 'complete' ||
				loaderProgressRef.current > 95
			) {
				setTestDriveLoaderProgress( 100 );
				const redirectUrl = addQueryArgs( overviewUrl, {
					'sandbox-onboarded': true,
				} );
				window.location.href = redirectUrl;
			} else {
				setTimeout( checkAccountStatus, 2000 );
			}
		} );
	};

	const trackConnectAccountClicked = ( sandboxMode: boolean ) => {
		recordEvent( 'wcpay_connect_account_clicked', {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
			is_new_onboarding_flow: isNewFlowEnabled,
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			sandbox_mode: sandboxMode,
			path: 'payments_connect_v2',
			source: determineTrackingSource(),
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

		const url = addQueryArgs( connectUrl, {
			test_mode: true,
			test_drive: true,
		} );

		const updateProgress = setInterval( updateLoaderProgress, 2500, 40, 5 );

		// If Jetpack is connected, we should proceed with AJAX onboarding.
		// Otherwise, redirect to the Jetpack connect screen.
		if ( wcpaySettings.isJetpackConnected ) {
			setTestDriveModeModalShown( true );
			fetch( url, {
				method: 'GET',
				redirect: 'follow',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
				},
			} ).then( ( response ) => {
				clearInterval( updateProgress );
				setTestDriveLoaderProgress( 40 );

				// Check the response url for the `wcpay-connection-success` parameter,
				// indicating a successful connection.
				const urlParams = new URLSearchParams( response.url );
				const connected =
					urlParams.get( 'wcpay-connection-success' ) || '';

				// The account has been successfully onboarded.
				// Start checking the account status every 2 seconds.
				// Once the status is complete, redirect to the Overview page.
				if ( connected === '1' ) {
					checkAccountStatus();
				} else {
					// Set the error message.
					setErrorMessage(
						__(
							'An error occurred while creating a sandbox account. Please try again!',
							'woocommerce-payments'
						)
					);

					// Scroll window to the top
					window.scrollTo( {
						top: 0,
					} );

					// Hide loader.
					setTestDriveModeModalShown( false );
					setTestDriveModeSubmitted( false );
				}
			} );
		} else {
			window.location.href = url;
		}
	};

	const forceOnboardTestDrive = () => {
		const urlParams = new URLSearchParams( window.location.search );
		const forceOnboard = urlParams.get( 'force-test-onboard' ) || false;

		// If the force test onboard is present and Jetpack is connected
		// we should start onboarding Test Drive account automatically.
		if ( forceOnboard && wcpaySettings.isJetpackConnected ) {
			handleSetupTestDriveMode();
		}
	};

	useEffect( () => {
		recordEvent( 'page_view', {
			path: 'payments_connect_v2',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			source: determineTrackingSource(),
		} );

		forceOnboardTestDrive();
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
			window.location.href = connectUrl;
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

		window.location.href = connectUrl;
	};

	return (
		<Page isNarrow className="connect-account-page">
			{ errorMessage && (
				<Notice
					className="wcpay-connect-error-notice"
					status="error"
					isDismissible={ false }
				>
					{ errorMessage }
				</Notice>
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
					{ devMode && <SandboxModeNotice /> }
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
								disabled={ isSubmitted }
								onClick={ handleSetup }
							>
								{ wcpaySettings.isJetpackConnected
									? strings.button.jetpack_connected
									: strings.button.jetpack_not_connected }
							</Button>
						</div>
					</Card>
					{ incentive && <Incentive { ...incentive } /> }
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
				</>
			) }
			{ isTestDriveModeModalShown && (
				<TestDriveLoader progress={ testDriveLoaderProgress } />
			) }
		</Page>
	);
};

export default ConnectAccountPage;
