/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, Link } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import './style.scss';

const ConnectAccountPage = () => {
	return (
		<div className="connect-account">
			<Card className="connect-account__card" >
				<p><Gridicon icon="credit-card" size={ 72 } /></p>
				<h2>
					{ wcpaySettings.strings.setupHeading }
				</h2>
				{ ! wcpaySettings.onBoardingDisabled ? (
				<>
				<p>
					<Link href={ wcpaySettings.tosUrl }>
						{ wcpaySettings.strings.setupTerms }
					</Link>
				</p>
				<p>
					<Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ wcpaySettings.strings.setupGetStarted }</Button>
				</p>
				<p>
					<Link href="admin.php?page=wc-settings&tab=checkout" type="wp-admin">
						{ __( 'Additional payment methods', 'woocommerce-payments' ) }
					</Link>
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
		</div>
	);
};

export default ConnectAccountPage;
