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
				<h2> { __( 'WooCommerce Payments', 'woocommerce-payments' ) } </h2>
				<p className="connect-account__description">
					{ __( 'Accept credit card payments the easy way! No set up fees. ' +
						'No monthly fees. Just 2.9% + $0.30 per transaction on U.S.-issued cards.', 'woocommerce-payments' ) }
				</p>
				{ ! wcpaySettings.onBoardingDisabled ? (
				<>
				<p className="connect-account__terms">
					{
						createInterpolateElement(
							__( 'By clicking “Set up,” you agree to the <a>Terms of Service</a>', 'woocommerce-payments' ),
							{
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								a: <a href="https://wordpress.com/tos" />,
							}
						)
					}
				</p>
				<hr className="full-width" />
				<p className="connect-account__action">
					<Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ __( 'Set up', 'woocommerce-payments' ) }</Button>
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
