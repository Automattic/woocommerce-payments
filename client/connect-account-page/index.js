/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button, Notice, CheckboxControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';
import strings from './strings';
import wcpayTracks from 'tracks';

const ConnectAccountPage = () => {
	const [ isSubmitted, setSubmitted ] = useState( false );
	const [ isUsageTrackingEnabled, setUsageTracking ] = useState(
		wcpayTracks.isEnabled()
	);

	const handleSetup = async () => {
		setSubmitted( true );

		// Use async version here to opt in for tracking and load the script if required.
		await wcpayTracks.recordEventAsync(
			wcpayTracks.events.CONNECT_ACCOUNT_CLICKED,
			null,
			isUsageTrackingEnabled
		);

		window.location = isUsageTrackingEnabled
			? wcpaySettings.connectUrl + '&enable-usage-tracking=true'
			: wcpaySettings.connectUrl;
	};

	return (
		<Page isNarrow className="connect-account">
			{ wcpaySettings.errorMessage && (
				<Notice
					className="wcpay-connect-error-notice"
					status="error"
					isDismissible={ false }
				>
					{ wcpaySettings.errorMessage }
				</Notice>
			) }
			<Card className="connect-account__card">
				<HeroImage className="hero-image" />
				<h2>{ strings.heading }</h2>
				<p className="connect-account__description">
					{ strings.description }
				</p>
				{ ! wcpaySettings.onBoardingDisabled ? (
					<>
						<p className="connect-account__terms">
							{ strings.terms }
						</p>
						{ ! wcpayTracks.isEnabled() ? (
							<div className="connect-account__usage-tracking">
								<CheckboxControl
									label={ strings.usageTrackingLabel }
									help={ strings.usageTrackingHelp }
									checked={ isUsageTrackingEnabled }
									onChange={ setUsageTracking }
									disabled={ isSubmitted }
								/>
							</div>
						) : null }
						<hr className="full-width" />
						<p className="connect-account__action">
							<Button
								isPrimary
								isLarge
								isBusy={ isSubmitted }
								disabled={ isSubmitted }
								onClick={ handleSetup }
							>
								{ __( 'Set up', 'woocommerce-payments' ) }
							</Button>
						</p>
					</>
				) : (
					<p>
						{ strings.onboardingDisabled[ 0 ] }
						<br />
						{ strings.onboardingDisabled[ 1 ] }
					</p>
				) }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
