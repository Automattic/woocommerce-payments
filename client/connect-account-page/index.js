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
					{ __(
						'Accept credit cards online using WooCommerce Payments. Verify your business details to begin receiving payments.',
						'woocommmerce-payments'
					) }
				</h2>
				<p><Button isPrimary isLarge href={ wcpaySettings.connectUrl }>{ __( 'Get started', 'woocommerce-payments' ) }</Button></p>
				<p>
					{ __( 'By clicking \'Get started\' you agree to WooCommerce Payments', 'woocommmerce-payments' ) }
					&nbsp;
					{ /* TODO: Update this once we have the TOS link */ }
					{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
					<a href="#">{ __( 'terms of service', 'woocommerce-payments' ) }</a>
					.
				</p>
				<p>
					<Link href="admin.php?page=wc-settings&tab=checkout" type="wp-admin">
						{ __( 'Additional payment methods', 'woocommerce-payments' ) }
					</Link>
				</p>
			</Card>
		</div>
	);
};

export default ConnectAccountPage;
