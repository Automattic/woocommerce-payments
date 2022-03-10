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
import AmericanExpress from './payment-methods/americanexpress';
import ApplePay from './payment-methods/applepay';
import DinersClub from './payment-methods/dinersclub';
import Discover from './payment-methods/discover';
import GiroPay from './payment-methods/giropay';
import GooglePay from './payment-methods/googlepay';
import JCB from './payment-methods/jcb';
import MasterCard from './payment-methods/mastercard';
import Sofort from './payment-methods/sofort';
import UnionPay from './payment-methods/unionpay';
import Visa from './payment-methods/visa';
import logoImg from '../../../assets/images/logo.svg';
import ManageImg from './illustrations/manage.svg';
import MulticurrencyImg from './illustrations/multicurrency.svg';
import PaymentsImg from './illustrations/payments.svg';
import './style.scss';

const PaymentMethods = () => (
	<div>
		<Visa />
		<MasterCard />
		<AmericanExpress />
		<ApplePay />
		<GooglePay />
		<GiroPay />
		<DinersClub />
		<Discover />
		<UnionPay />
		<JCB />
		<Sofort />
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
