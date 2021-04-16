/** @format */
/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';
import { Button, Notice } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import strings from './strings';
import wcpayTracks from 'tracks';
import HeroImage from './hero-image.js';
import WCPayLogo from './wcpay-logo.js';
import Visa from './cards/visa.js';
import MasterCard from './cards/mastercard.js';
import Maestro from './cards/maestro.js';
import Amex from './cards/amex.js';
import ApplePay from './cards/applepay.js';
import CB from './cards/cb.js';
import DinersClub from './cards/diners.js';
import Discover from './cards/discover.js';
import JCB from './cards/jcb.js';
import UnionPay from './cards/unionpay.js';

const Masthead = () => (
	<header className="wcpay-connect-account-page-masthead">
		<h1>
			<WCPayLogo />
		</h1>
		<div className="wcpay-connect-account-page-recommended">
			<Pill>{ strings.recommended }</Pill>
		</div>
		<div className="wcpay-connect-account-page-hero">
			<HeroImage />
		</div>
	</header>
);

const Pill = ( props ) => (
	<div className="wcpay-connect-account-page-pill">{ props.children }</div>
);

const LearnMore = () => {
	const handleClick = () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.CONNECT_ACCOUNT_LEARN_MORE
		);
	};
	return (
		<a onClick={ handleClick } href="https://woocommerce.com/payments/">
			{ strings.learnMore }
		</a>
	);
};

const PaymentMethods = () => (
	<div className="wcpay-connect-account-page-payment-methods">
		<Visa />
		<MasterCard />
		<Maestro />
		<Amex />
		<DinersClub />
		<CB />
		<Discover />
		<UnionPay />
		<JCB />
		<ApplePay />
	</div>
);

const StepNumber = ( props ) => (
	<span className="wcpay-connect-account-page-step-number">
		{ props.children }
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
		const isConnected = useSelect( ( select ) => {
			return select( wc.data.PLUGINS_STORE_NAME ).isJetpackConnected() // eslint-disable-line no-undef
				? 'Yes'
				: 'No';
		} );
		wcpayTracks.recordEvent( wcpayTracks.events.CONNECT_ACCOUNT_CLICKED, {
			wpcom_connection: isConnected, // eslint-disable-line camelcase
		} );
	};

	return (
		<>
			<h2>{ strings.onboarding[ 0 ] }</h2>
			<p>
				{ strings.onboarding[ 1 ] }
				<br />
				<LearnMore />
			</p>

			<h3>{ strings.paymentMethodsHeading }</h3>

			<PaymentMethods />

			<hr className="full-width" />

			<p className="connect-account__action">
				<Button
					isPrimary
					isLarge
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
					<h3>{ strings.step1[ 0 ] }</h3>
					<p>{ strings.step1[ 1 ] }</p>
				</div>
				<div className="connect-page-onboarding-steps-item">
					<StepNumber>2</StepNumber>
					<h3>{ strings.step2[ 0 ] }</h3>
					<p>{ strings.step2[ 1 ] }</p>
				</div>
				<div className="connect-page-onboarding-steps-item">
					<StepNumber>3</StepNumber>
					<h3>{ strings.step3[ 0 ] }</h3>
					<p>{ strings.step3[ 1 ] }</p>
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
		<Page isNarrow className="connect-account">
			<ConnectPageError />
			<Card className="connect-account__card">
				<Masthead />
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
	);
};

export default ConnectAccountPage;
