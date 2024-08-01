/** @format **/

/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
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

const SandboxModeNotice = () => (
	<BannerNotice icon status="warning" isDismissible={ false }>
		{ strings.sandboxModeNotice }
	</BannerNotice>
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
	const [ isSandboxModeClicked, setSandboxModeClicked ] = useState( false );
	const {
		connectUrl,
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

	useEffect( () => {
		recordEvent( 'page_view', {
			path: 'payments_connect_v2',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			source: determineTrackingSource(),
		} );
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

	const handleEnableSandboxMode = async () => {
		setSandboxModeClicked( true );

		trackConnectAccountClicked( true );

		const url = addQueryArgs( connectUrl, {
			test_mode: true,
			create_builder_account: true,
		} );
		window.location.href = url;
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
								isBusy={ isSandboxModeClicked }
								disabled={ isSandboxModeClicked }
								onClick={ handleEnableSandboxMode }
							>
								{ strings.button.sandbox }
							</Button>
						</PanelBody>
					</Panel>
				</>
			) }
		</Page>
	);
};

export default ConnectAccountPage;
