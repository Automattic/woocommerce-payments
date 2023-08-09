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
	CardDivider,
	Notice,
	Icon,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { payment } from '@wordpress/icons';
import globe from 'gridicons/dist/globe';
import scheduled from 'gridicons/dist/scheduled';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import Page from 'components/page';
import PaymentMethods from './payment-methods';
import Incentive from './incentive';
import InfoNotice from './info-notice-modal';
import OnboardingLocationCheckModal from './modal';
import LogoImg from 'assets/images/woopayments.svg?asset';
import strings from './strings';
import './style.scss';

const ConnectAccountPage: React.FC = () => {
	const firstName = wcSettings.admin?.currentUserData?.first_name;
	const incentive = wcpaySettings.connectIncentive;
	const isNewFlowEnabled =
		wcpaySettings.progressiveOnboarding?.isNewFlowEnabled;

	const [ errorMessage, setErrorMessage ] = useState< string >(
		wcpaySettings.errorMessage
	);
	const [ isSubmitted, setSubmitted ] = useState( false );
	const {
		connectUrl,
		connect: { availableCountries, country },
	} = wcpaySettings;

	useEffect( () => {
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_VIEW, {
			path: 'payments_connect_v2',
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			woo_country_code:
				wcSettings?.preloadSettings?.general
					?.woocommerce_default_country ||
				wcSettings?.admin?.preloadSettings?.general
					?.woocommerce_default_country,
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

	const handleSetup = async () => {
		setSubmitted( true );

		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
			is_new_onboarding_flow: isNewFlowEnabled,
			...( incentive && {
				incentive_id: incentive.id,
			} ),
			woo_country_code:
				wcSettings?.preloadSettings?.general
					?.woocommerce_default_country ||
				wcSettings?.admin?.preloadSettings?.general
					?.woocommerce_default_country,
		} );

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
		if ( ! availableCountries[ country ] ) {
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
					<Card>
						<div className="connect-account-page__heading">
							<img src={ LogoImg } alt="logo" />
							<h2>{ strings.heading( firstName ) }</h2>
						</div>
						<div className="connect-account-page__content">
							<div className="connect-account-page__content-usp">
								<Icon icon={ payment } />
								{ strings.usp1 }
								<Icon icon={ globe } />
								{ strings.usp2 }
								<Icon icon={ scheduled } />
								{ strings.usp3 }
							</div>
							<Button
								variant="primary"
								isBusy={ isSubmitted }
								disabled={ isSubmitted }
								onClick={ handleSetup }
							>
								{ strings.button }
							</Button>
							<p>
								{ wcpaySettings.isWooPayStoreCountryAvailable
									? strings.agreementWithWooPay
									: strings.agreement }
							</p>
						</div>
						<CardDivider />
						<div className="connect-account-page__payment-methods">
							<PaymentMethods />
						</div>
					</Card>
					{ incentive && <Incentive { ...incentive } /> }
					<Card className="connect-account-page__details">
						<h2>{ strings.stepsHeading }</h2>
						<InfoNotice />
						<div className="connect-account-page__steps">
							<div className="connect-account-page__step">
								<span>1</span>
								<h3>{ strings.step1.heading }</h3>
								<p>{ strings.step1.description }</p>
							</div>
							<div className="connect-account-page__step">
								<span>2</span>
								<h3>{ strings.step2.heading }</h3>
								<p>{ strings.step2.description }</p>
							</div>
							<div className="connect-account-page__step">
								<span>3</span>
								<h3>{ strings.step3.heading }</h3>
								<p>{ strings.step3.description }</p>
							</div>
						</div>
					</Card>
				</>
			) }
		</Page>
	);
};

export default ConnectAccountPage;
