/** @format */
/**
 * External dependencies
 */
import React from 'react';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { render, useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import OnboardingLocationCheckModal from 'connect-account-page/modal';
import Page from 'components/page';
import wcpayTracks from 'tracks';
import strings from './strings';
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
import LogoImg from 'assets/images/logo.svg?asset';
import ManageImg from 'assets/images/illustrations/manage.svg?asset';
import MulticurrencyImg from 'assets/images/illustrations/multicurrency.svg?asset';
import PaymentsImg from 'assets/images/illustrations/payments.svg?asset';
import './style.scss';

const PaymentMethods = () => (
	<div>
		<img src={ Visa } alt="Visa" />
		<img src={ MasterCard } alt="MasterCard" />
		<img src={ AmericanExpress } alt="American Express" />
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
	<Card>
		<CardBody>{ strings.onboardingDisabled }</CardBody>
	</Card>
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
			<CardHeader>
				<img src={ LogoImg } alt="logo" />
			</CardHeader>
			<CardBody className="connect-account-page-treatment__content">
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
			<CardBody className="connect-account-page-treatment__payment-methods">
				<p>{ strings.acceptedPaymentMethods }</p>
				<PaymentMethods />
			</CardBody>
		</>
	);
};

const ConnectPageOnboardingDetails = () => {
	return (
		<>
			<div>
				<img src={ PaymentsImg } alt="Payments" />
				<p>{ strings.detailsPayments }</p>
			</div>
			<div>
				<img src={ MulticurrencyImg } alt="Multi-Currency" />
				<p>{ strings.detailsMulticurrency }</p>
			</div>
			<div>
				<img src={ ManageImg } alt="Manage" />
				<p>{ strings.detailsManage }</p>
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
		<Page isNarrow className="connect-account-page-treatment">
			<ConnectPageError />
			{ wcpaySettings.onBoardingDisabled ? (
				<ConnectPageOnboardingDisabled />
			) : (
				<>
					<Card className="connect-account-page-treatment__onboarding">
						<ConnectPageOnboarding />
					</Card>
					<Card>
						<CardBody className="connect-account-page-treatment__details">
							<ConnectPageOnboardingDetails />
						</CardBody>
					</Card>
				</>
			) }
		</Page>
	);
};

export default ConnectAccountPage;
