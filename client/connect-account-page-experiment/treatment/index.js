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
import AmericanExpress from 'assets/images/cards/amex.svg';
import ApplePay from 'assets/images/cards/apple-pay.svg';
import DinersClub from 'assets/images/cards/diners.svg';
import Discover from 'assets/images/cards/discover.svg';
import GiroPay from 'assets/images/payment-methods/giropay.svg';
import GooglePay from 'assets/images/cards/google-pay.svg';
import JCB from 'assets/images/cards/jcb.svg';
import MasterCard from 'assets/images/cards/mastercard.svg';
import Sofort from 'assets/images/payment-methods/sofort.svg';
import UnionPay from 'assets/images/cards/unionpay.svg';
import Visa from 'assets/images/cards/visa.svg';
import logoImg from '../../../assets/images/logo.svg';
import ManageImg from './illustrations/manage.svg';
import MulticurrencyImg from './illustrations/multicurrency.svg';
import PaymentsImg from './illustrations/payments.svg';
import './style.scss';

const PaymentMethods = () => (
	<div>
		<img src={ Visa } alt="Visa" />
		<img src={ MasterCard } alt="MasterCard" />
		<img src={ AmericanExpress } alt="AmericanExpress" />
		<img src={ ApplePay } alt="ApplePay" />
		<img src={ GooglePay } alt="GooglePay" />
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
				<img src={ logoImg } alt={ 'WooCommerce Payments logo' } />
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
