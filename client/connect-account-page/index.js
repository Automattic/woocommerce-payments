/** @format */
/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';
import { Button, Notice } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import strings from './strings';
import wcpayTracks from 'tracks';
import Banner from '../banner';
import Visa from './cards/visa.js';
import MasterCard from './cards/mastercard.js';
import Amex from './cards/amex.js';
import ApplePay from './cards/applepay.js';
import CB from './cards/cb.js';
import DinersClub from './cards/diners.js';
import Discover from './cards/discover.js';
import GPay from './cards/gpay.js';
import JCB from './cards/jcb.js';
import UnionPay from './cards/unionpay.js';

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
		<Visa />
		<MasterCard />
		<Amex />
		<DinersClub />
		<CB />
		<Discover />
		<UnionPay />
		<JCB />
		<GPay />
		<ApplePay />
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

	const handleSetup = () => {
		setSubmitted( true );
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			// eslint-disable-next-line camelcase
			wpcom_connection: wcpaySettings.isJetpackConnected ? 'Yes' : 'No',
		} );
	};

	return (
		<>
			<h2>{ strings.onboarding.heading }</h2>
			<p>
				{ strings.onboarding.description }
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
					href={ wcpaySettings.connectUrl }
				>
					{ strings.button }
				</Button>
			</p>
		</>
	);
};

const ConnectPageOnboardingSteps = () => {
	return (
		<>
			<h2>{ strings.stepsHeading }</h2>
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
					<Banner style="account-page" />
					<div className="content">
						{ wcpaySettings.onBoardingDisabled ? (
							<ConnectPageOnboardingDisabled />
						) : (
							<ConnectPageOnboarding />
						) }
					</div>
				</Card>
				{ ! wcpaySettings.onBoardingDisabled && (
					<Card className="connect-account__steps">
						<ConnectPageOnboardingSteps />
					</Card>
				) }
			</Page>
		</div>
	);
};

export default ConnectAccountPage;
