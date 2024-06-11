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
	Spinner,
	Flex,
	FlexItem,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';
import Page from 'components/page';
import BannerNotice from 'components/banner-notice';
import PaymentMethods from './payment-methods';
import Incentive from './incentive';
import InfoNotice from './info-notice-modal';
import ConnectUnsupportedAccountPage from './unsupported-country';
import OnboardingLocationCheckModal from './modal';
import LogoImg from 'assets/images/woopayments.svg?asset';
import strings from './strings';
import './style.scss';
import InlineNotice from 'components/inline-notice';
import RegionPicker from './region-picker';
import { Apm, suggestedApmsResponseInterface } from './types';
import { NAMESPACE } from 'wcpay/data/constants';

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

	const [ isLoading, setLoading ] = useState( false );
	const [ storeCountry, setStoreCountry ] = useState( country );
	const [ suggestedApms, setSuggestedApms ] = useState(
		wcMarketplaceSuggestions || {}
	);

	const isCountrySupported = !! availableCountries[ storeCountry ];

	useEffect( () => {
		recordEvent( 'page_view', {
			path: 'payments_connect_v2',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
		} );
		// We only want to run this once.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const handleLocationChange = async ( countryID: string ) => {
		try {
			setLoading( true );

			const response = await apiFetch< suggestedApmsResponseInterface >( {
				path: `${ NAMESPACE }/settings/get_apms/${ countryID }`,
				method: 'GET',
			} );

			setSuggestedApms( response );
			setStoreCountry( countryID );
		} finally {
			setLoading( false );
		}
	};

	const getSuggestedApms = (): Apm[] => {
		const {
			paymentGatewaySuggestions = [],
			activePlugins = [],
		} = suggestedApms;

		return paymentGatewaySuggestions
			.filter( ( apm: Apm ) => apm.plugins && apm.plugins.length > 0 )
			.filter(
				( apm: Apm ) =>
					! Object.values( activePlugins ).includes(
						apm.plugins[ 0 ]
					)
			);
	};

	const trackConnectAccountClicked = ( sandboxMode: boolean ) => {
		recordEvent( 'wcpay_connect_account_clicked', {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
			is_new_onboarding_flow: isNewFlowEnabled,
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			sandbox_mode: sandboxMode,
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

		const url = addQueryArgs( connectUrl, {
			country: storeCountry,
		} );

		window.location.href = url;
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
			{ isLoading && (
				<Flex
					direction="column"
					className="connect-account-page__loading"
				>
					<FlexItem>
						<Spinner />
					</FlexItem>
				</Flex>
			) }
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
					{ devMode && <SandboxModeNotice /> }
					{ ! isCountrySupported && (
						<ConnectUnsupportedAccountPage
							country={ storeCountry }
							setStoreCountry={ handleLocationChange }
							suggestedApms={ getSuggestedApms() }
						/>
					) }
					{ isCountrySupported && (
						<>
							<Card>
								<div className="connect-account-page__heading">
									<div className="connect-account-page__heading--wrapper">
										<img src={ LogoImg } alt="logo" />
										<RegionPicker
											country={ storeCountry }
											setStoreCountry={
												handleLocationChange
											}
										/>
									</div>

									<h2>{ strings.heading( firstName ) }</h2>
								</div>
								<div className="connect-account-page__content">
									<InfoNotice />
								</div>
								<div className="connect-account-page__payment-methods">
									<PaymentMethods />
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
											: strings.button
													.jetpack_not_connected }
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
				</>
			) }
		</Page>
	);
};

export default ConnectAccountPage;
