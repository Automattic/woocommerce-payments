/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import './style.scss';
import Page from 'components/page';
import HeroImage from './hero-image';

const ConnectAccountPage = () => {
	return (
		<Page isNarrow className="connect-account">
			<Card className="connect-account__card">
				<HeroImage />
				<h2>
					{ __( 'Accept credit cards online using WooCommerce Payments.', 'woocommerce-payments' ) }
					<br />
					{ __( 'Simply verify your business details to get started.', 'woocommerce-payments' ) }
				</h2>
				{ ! wcpaySettings.onBoardingDisabled ? (
				<>
				<p className="connect-account__terms">
					{
						createInterpolateElement(
							__( 'By clicking “Verify details,” you agree to the <a>Terms of Service</a>.', 'woocommerce-payments' ),
							{
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								a: <a href="https://wordpress.com/tos" />,
							}
						)
					}
				</p>
				<hr className="full-width" />
				<p className="connect-account__action">
					<Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ __( 'Verify details', 'woocommerce-payments' ) }</Button>
				</p>
				</>
				) : (
				<p>
					{ __( 'We\'ve temporarily paused new account creation.', 'woocommmerce-payments' ) }
					<br />
					{ __( 'We\'ll notify you when we resume!', 'woocommmerce-payments' ) }
				</p>
				) }
			</Card>
		</Page>
	);
};

export default ConnectAccountPage;
