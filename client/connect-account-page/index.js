/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';

const ConnectAccountPage = () => {
	const [ isSubmitted, setSubmitted ] = useState( false );

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
				<HeroImage />
				<h2>
					{ ' ' }
					{ __(
						'WooCommerce Payments',
						'woocommerce-payments'
					) }{ ' ' }
				</h2>
				<p className="connect-account__description">
					{ __(
						'Accept credit card payments the easy way! No set up fees. ' +
							'No monthly fees. Just 2.9% + $0.30 per transaction on U.S.-issued cards.',
						'woocommerce-payments'
					) }
				</p>
				{ ! wcpaySettings.onBoardingDisabled ? (
					<>
						<p className="connect-account__terms">
							{ createInterpolateElement(
								__(
									'By clicking “Set up,” you agree to the <a>Terms of Service</a>',
									'woocommerce-payments'
								),
								{
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									a: <a href="https://wordpress.com/tos" />,
								}
							) }
						</p>
						<hr className="full-width" />
						<p className="connect-account__action">
							<Button
								isPrimary
								isLarge
								isBusy={ isSubmitted }
								disabled={ isSubmitted }
								onClick={ () => {
									setSubmitted( true );
									// We have to manually update location to wait while tracking script is loaded if tracking is disabled.
									if (
										! window.wcTracks.isEnabled &&
										'function' ===
											typeof window.wcTracks.enable
									) {
										window.wcTracks.enable(
											( scriptLoaded ) => {
												if ( scriptLoaded ) {
													window.wcTracks.recordEvent(
														'wcpay_connect_account_clicked'
													);
												}
												window.location =
													wcpaySettings.connectUrl;
											}
										);
									} else {
										window.wcTracks.recordEvent(
											'wcpay_connect_account_clicked'
										);
										window.location =
											wcpaySettings.connectUrl;
									}
								} }
							>
								{ __( 'Set up', 'woocommerce-payments' ) }
							</Button>
						</p>
					</>
				) : (
					<p>
						{ __(
							"We've temporarily paused new account creation.",
							'woocommmerce-payments'
						) }
						<br />
						{ __(
							"We'll notify you when we resume!",
							'woocommmerce-payments'
						) }
					</p>
				) }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
