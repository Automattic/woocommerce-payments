/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';
import strings from './strings';
import wcpayTracks from 'tracks';

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
			<p className="connect-account__terms">{ strings.terms }</p>
			<hr className="full-width" />
			<p className="connect-account__action">
				<Button
					isPrimary
					isBusy={ isSubmitted }
					disabled={ isSubmitted }
					onClick={ handleSetup }
					href={ wcpaySettings.connectUrl }
				>
					{ __( 'Set up', 'woocommerce-payments' ) }
				</Button>
			</p>
		</>
	);
};

const ConnectAccountPage = () => {
	return (
		<Page isNarrow className="connect-account">
			<ConnectPageError />
			<Card className="connect-account__card">
				<HeroImage className="hero-image" />
				<h2>{ strings.heading }</h2>
				<p className="connect-account__description">
					{ strings.description }
				</p>
				{ wcpaySettings.onBoardingDisabled ? (
					<ConnectPageOnboardingDisabled />
				) : (
					<ConnectPageOnboarding />
				) }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
