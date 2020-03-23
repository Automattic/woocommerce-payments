/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';
import apiFetch from "@wordpress/api-fetch";

const ConnectAccountPage = () => {
	const [ isJetpackRegistered, setJetpackRegistered ] = useState( wcpaySettings.isJetpackRegistered );
	const [ jetpackRegistrationInProgress, setJetpackRegistrationInProgress ] = useState( false );
	const [ jetpackConnectURL, setJetpackConnectURL ] = useState( wcpaySettings.jetpackConnectURL );
	const [ isJetpackConnected, setJetpackConnected ] = useState( wcpaySettings.isJetpackRegistered && ! wcpaySettings.jetpackConnectURL );

	useEffect( () => {
		const handler = async event => {
			if ( 'close' === event.data && jetpackConnectURL ) {
				const result = await apiFetch( { path: '/wc/v3/payments/jetpack/check-stripe-connection', method: 'get' } );
				if ( result.isConnected ) {
					window.location = result.redirectUrl;
				} else {
					setJetpackConnected( true );
					setJetpackConnectURL( null );
				}
			}
		};
		window.addEventListener( 'message', handler );
		return () => window.removeEventListener( 'message', handler );
	}, [ jetpackConnectURL ] );

	const doRegisterJetpack = async () => {
		setJetpackRegistrationInProgress( true );
		try {
			const result = await apiFetch( { path: '/wc/v3/payments/jetpack/register-site', method: 'post' } );
			setJetpackRegistered( true );
			setJetpackConnectURL( result.connectUrl );
		} catch ( err ) {
			// TODO: real error handling.
			console.error( err );
			alert( err );
		} finally {
			setJetpackRegistrationInProgress( false );
		}
	};

	const renderOnboardingDisabled = () => {
		return (
			<p>
				{ __( 'We\'ve temporarily paused new account creation.', 'woocommmerce-payments' ) }
				<br />
				{ __( 'We\'ll notify you when we resume!', 'woocommmerce-payments' ) }
			</p>
		);
	};

	const renderStripeOnboarding = () => {
		return (
			<>
				<p className="connect-account__terms" dangerouslySetInnerHTML={ { __html: wcpaySettings.strings.setupTerms } } />
				<hr className="full-width" />
				<p className="connect-account__action">
					<Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ wcpaySettings.strings.setupGetStarted }</Button>
				</p>
			</>
		);
	};

	const renderJetpackConnection = ( url ) => {
		return (
			<>
				<center>You need to connect to Jetpack first. You accept the ToS and blah blah blah.</center>
				<iframe className="jp-jetpack-connect__iframe"
						src={ url }
						style={{
							width: '100%',
							background: 'white',
							height: '250px',
							paddingTop: '30px',
						}} />
			</>
		);
	};

	const renderJetpackRegister = ( isInProgress ) => {
		return (
			<>
				<center>You need to connect to Jetpack first. You accept the ToS and blah blah blah.</center>
				<p className="connect-account__action">
					<Button
						isPrimary
						isLarge
						isBusy={ isInProgress }
						disabled={ isInProgress }
						onClick={ doRegisterJetpack }>
							{ __( 'Connect', 'woocommerce-payments' ) }
					</Button>
				</p>
			</>
		);
	};

	const renderPageContent = () => {
		if ( wcpaySettings.onBoardingDisabled ) {
			return renderOnboardingDisabled();
		} else if ( isJetpackConnected ) {
			return renderStripeOnboarding();
		} else if ( isJetpackRegistered ) {
			return renderJetpackConnection( jetpackConnectURL );
		} else {
			return renderJetpackRegister( jetpackRegistrationInProgress );
		}
	};

	return (
		<Page isNarrow className="connect-account">
			<Card className="connect-account__card">
				<HeroImage />
				<h2>
					{ wcpaySettings.strings.setupHeadings.map( ( heading, i ) => ( <span key={ i }>{ heading }<br /></span> ) ) }
				</h2>
				{ renderPageContent() }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
