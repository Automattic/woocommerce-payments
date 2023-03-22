/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
import { render, useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import OnboardingLocationCheckModal from './modal';
import OnboardingMoreInfoModal from './info-modal';
import Page from 'components/page';
import strings from './strings';
import wcpayTracks from 'tracks';
import Banner from '../banner';
import Visa from 'assets/images/cards/visa.svg?asset';
import MasterCard from 'assets/images/cards/mastercard.svg?asset';
import Amex from 'assets/images/cards/amex.svg?asset';
import ApplePay from 'assets/images/cards/apple-pay.svg?asset';
import DinersClub from 'assets/images/cards/diners.svg?asset';
import Discover from 'assets/images/cards/discover.svg?asset';
import GPay from 'assets/images/cards/google-pay.svg?asset';
import JCB from 'assets/images/cards/jcb.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import LightbulbIcon from 'assets/images/icons/lightbulb.svg?asset';
import './style.scss';

const LearnMore = () => {
	const handleClick = () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.CONNECT_ACCOUNT_LEARN_MORE
		);
	};
	return (
		<a
			onClick={ handleClick }
			href="https://woocommerce.com/payments/"
			target="_blank"
			rel="noreferrer"
		>
			{ strings.learnMore }
		</a>
	);
};

const PaymentMethods = () => (
	<div className="wcpay-connect-account-page-payment-methods">
		<img src={ Visa } alt="Visa" />
		<img src={ MasterCard } alt="MasterCard" />
		<img src={ Amex } alt="Amex" />
		<img src={ DinersClub } alt="DinersClub" />
		<img src={ Discover } alt="Discover" />
		<img src={ UnionPay } alt="UnionPay" />
		<img src={ JCB } alt="JCB" />
		<img src={ GPay } alt="Google Pay" />
		<img src={ ApplePay } alt="Apple Pay" />
	</div>
);

const StepNumber = ( props ) => (
	<span className="wcpay-connect-account-page-step-number">
		{ props.children }
	</span>
);

const TermsOfService = () => (
	<span className="wcpay-connect-account-page-terms-of-service">
		{ strings.terms }
	</span>
);

const ConnectPageError = () => {
	if ( ! wcpaySettings.errorMessage ) {
		return null;
	}
	return (
		<Notice
			className="wcpay-connect-error-notice"
			status="error"
			isDismissible={ false }
		>
			{ wcpaySettings.errorMessage }
		</Notice>
	);
};

const ConnectPageOnboardingDisabled = () => (
	<p>
		{ strings.onboardingDisabled[ 0 ] }
		<br />
		{ strings.onboardingDisabled[ 1 ] }
	</p>
);

const ConnectPageOnboarding = () => {
	const [ isSubmitted, setSubmitted ] = useState( false );
	const {
		connectUrl,
		connect: { availableCountries, country },
	} = wcpaySettings;

	const handleLocationCheck = () => {
		// Reset the 'Set up' button state if merchant decided to stop
		const handleModalDeclined = () => {
			setSubmitted( false );
		};
		// Redirect the merchant if merchant decided to continue
		const handleModalConfirmed = () => {
			window.location = connectUrl;
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

	const handleSetup = ( event ) => {
		const isCountryAvailable = availableCountries[ country ] !== undefined;
		if ( ! isCountryAvailable ) {
			// Inform the merchant if country specified in business address is not yet supported, but allow to proceed.
			event.preventDefault();
			handleLocationCheck( availableCountries );
		}

		setSubmitted( true );
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
		} );
	};

	return (
		<>
			<h2>{ strings.onboarding.heading }</h2>
			<p>
				{ 'US' === country
					? strings.onboarding.descriptionUS
					: strings.onboarding.description }
				<br />
				<LearnMore />
			</p>

			<h3>{ strings.paymentMethodsHeading }</h3>

			<PaymentMethods />

			<hr className="full-width" />

			<p className="connect-account__action">
				<TermsOfService />
				<Button
					isPrimary
					isBusy={ isSubmitted }
					disabled={ isSubmitted }
					onClick={ handleSetup }
					href={ connectUrl }
				>
					{ strings.button }
				</Button>
			</p>
		</>
	);
};

const ConnectPageInfoNotice = () => {
	const [ isModalOpen, setModalOpen ] = useState( false );

	return (
		<>
			<div className="wcpay-connect-info-notice">
				<img src={ LightbulbIcon } alt="light bulb icon" />
				<div>
					{ strings.infoNotice.description }
					<Button
						onClick={ () => {
							wcpayTracks.recordEvent(
								wcpayTracks.events
									.CONNECT_ACCOUNT_KYC_MODAL_OPENED
							);
							setModalOpen( true );
						} }
					>
						{ strings.infoNotice.button }
					</Button>
				</div>
			</div>
			{ isModalOpen && (
				<OnboardingMoreInfoModal
					handleModalClose={ () => setModalOpen( false ) }
				/>
			) }
		</>
	);
};

const ConnectPageOnboardingSteps = () => {
	return (
		<>
			<h2>{ strings.stepsHeading }</h2>
			<ConnectPageInfoNotice />
			<div className="connect-page-onboarding-steps">
				<div className="connect-page-onboarding-steps-item">
					<StepNumber>1</StepNumber>
					<h3>{ strings.step1.heading }</h3>
					<p>{ strings.step1.description }</p>
				</div>
				<div className="connect-page-onboarding-steps-item">
					<StepNumber>2</StepNumber>
					<h3>{ strings.step2.heading }</h3>
					<p>{ strings.step2.description }</p>
				</div>
				<div className="connect-page-onboarding-steps-item">
					<StepNumber>3</StepNumber>
					<h3>{ strings.step3.heading }</h3>
					<p>{ strings.step3.description }</p>
				</div>
			</div>
		</>
	);
};

const ConnectAccountPage = () => {
	useEffect( () => {
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_VIEW, {
			path: 'payments_connect_v2',
		} );
	}, [] );

	return (
		<div className="connect-account-page">
			<Page isNarrow className="connect-account">
				<ConnectPageError />
				<Card className="connect-account__card">
					<CardBody>
						<Banner style="account-page" />
						<div className="content">
							{ wcpaySettings.onBoardingDisabled ? (
								<ConnectPageOnboardingDisabled />
							) : (
								<ConnectPageOnboarding />
							) }
						</div>
					</CardBody>
				</Card>
				{ ! wcpaySettings.onBoardingDisabled && (
					<Card className="connect-account__steps">
						<CardBody>
							<ConnectPageOnboardingSteps />
						</CardBody>
					</Card>
				) }
			</Page>
		</div>
	);
};

export default ConnectAccountPage;
