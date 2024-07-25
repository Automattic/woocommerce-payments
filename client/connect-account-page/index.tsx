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
import { sanitizeHTML } from 'wcpay/utils/sanitize';

const SandboxModeNotice = () => (
	<BannerNotice icon status="warning" isDismissible={ false }>
		{ strings.sandboxModeNotice }
	</BannerNotice>
);

const ConnectAccountPage: React.FC = () => {
	const firstName = wcSettings.admin?.currentUserData?.first_name;
	const incentive = wcpaySettings.connectIncentive;

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

	const urlParams = new URLSearchParams( window.location.search );
	const urlSource = urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' );
	const urlFrom = urlParams.get( 'from' ) || '';

	const determineTrackingSource = () => {
		// If we have a source query param, use that.
		if ( !! urlSource && 'unknown' !== urlSource ) {
			return urlSource;
		}
		// Determine what source to use for the onboarding process.
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

	const trackConnectAccountClicked = ( sandboxMode: boolean ) => {
		recordEvent( 'wcpay_connect_account_clicked', {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
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

		window.location.href = addQueryArgs( connectUrl, {
			source: determineTrackingSource(),
			from: 'WCPAY_CONNECT',
		} );
	};

	const handleEnableSandboxMode = async () => {
		setSandboxModeClicked( true );

		trackConnectAccountClicked( true );

		window.location.href = addQueryArgs( connectUrl, {
			test_mode: 'true',
			create_builder_account: 'true',
			source: determineTrackingSource(),
			from: 'WCPAY_CONNECT',
		} );
	};

	return (
		<Page isNarrow className="connect-account-page">
			{ errorMessage && (
				<Notice
					className="wcpay-connect-error-notice"
					status="error"
					isDismissible={ false }
				>
					<div
						// eslint-disable-next-line react/no-danger
						dangerouslySetInnerHTML={ sanitizeHTML( errorMessage ) }
					></div>
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
									<p>Deposits</p>
									<span>Automatic - Daily</span>
								</div>
								<div className="connect-account-page__payment-methods__description__divider"></div>
								<div>
									<p>Payments capture</p>
									<span>Capture on order</span>
								</div>
								<div className="connect-account-page__payment-methods__description__divider"></div>
								<div>
									<p>Recurring payments</p>
									<span>Supported</span>
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
