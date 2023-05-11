/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { render } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CardDivider,
	Notice,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import Page from 'components/page';
import InfoNotice from './info-notice-modal';
import OnboardingLocationCheckModal from './modal';
import LogoImg from 'assets/images/logo.svg?asset';
import strings from './strings';
import './style.scss';

// Payment method icons
import AmericanExpress from 'assets/images/cards/amex.svg?asset';
import ApplePay from 'assets/images/cards/apple-pay.svg?asset';
import DinersClub from 'assets/images/cards/diners.svg?asset';
import Discover from 'assets/images/cards/discover.svg?asset';
import GiroPay from 'assets/images/payment-methods/giropay.svg?asset';
import GooglePay from 'assets/images/cards/google-pay.svg?asset';
import JCB from 'assets/images/cards/jcb.svg?asset';
import MasterCard from 'assets/images/cards/mastercard.svg?asset';
import Sofort from 'assets/images/payment-methods/sofort.svg?asset';
import UnionPay from 'assets/images/cards/unionpay.svg?asset';
import Visa from 'assets/images/cards/visa.svg?asset';

const ConnectAccountPage: React.FC = () => {
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

	const handleSetup = ( event: React.SyntheticEvent ) => {
		const isCountryAvailable = availableCountries[ country ] !== undefined;
		if ( ! isCountryAvailable ) {
			// Inform the merchant if country specified in business address is not yet supported, but allow to proceed.
			event.preventDefault();
			handleLocationCheck();
		}

		setSubmitted( true );
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
		} );
	};

	return (
		<Page isNarrow className="connect-account-page">
			{ wcpaySettings.errorMessage && (
				<Notice
					className="wcpay-connect-error-notice"
					status="error"
					isDismissible={ false }
				>
					{ wcpaySettings.errorMessage }
				</Notice>
			) }
			{ wcpaySettings.onBoardingDisabled ? (
				<Card>
					<CardBody>{ strings.onboardingDisabled }</CardBody>
				</Card>
			) : (
				<>
					<Card className="connect-account-page__onboarding">
						<CardHeader>
							<img src={ LogoImg } alt="logo" />
						</CardHeader>
						<CardBody className="connect-account-page__content">
							<h2>{ strings.heading }</h2>
							<p>{ strings.description }</p>
							<Button
								isPrimary
								isBusy={ isSubmitted }
								disabled={ isSubmitted }
								onClick={ handleSetup }
								href={ connectUrl }
							>
								{ strings.button }
							</Button>
						</CardBody>
						<CardDivider />
						<CardBody className="connect-account-page__payment-methods">
							<p>{ strings.acceptedPaymentMethods }</p>
							<div className="connect-account-page__payment-methods__icons">
								<img src={ Visa } alt="Visa" />
								<img src={ MasterCard } alt="MasterCard" />
								<img
									src={ AmericanExpress }
									alt="American Express"
								/>
								<img src={ ApplePay } alt="Apple Pay" />
								<img src={ GooglePay } alt="Google Pay" />
								<img src={ GiroPay } alt="GiroPay" />
								<img src={ DinersClub } alt="DinersClub" />
								<img src={ Discover } alt="Discover" />
								<img src={ UnionPay } alt="UnionPay" />
								<img src={ JCB } alt="JCB" />
								<img src={ Sofort } alt="Sofort" />
								<span>& more.</span>
							</div>
						</CardBody>
					</Card>
					<Card className="connect-account-page__details">
						<CardBody>
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
						</CardBody>
					</Card>
				</>
			) }
		</Page>
	);
};

export default ConnectAccountPage;
