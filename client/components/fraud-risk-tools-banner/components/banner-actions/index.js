/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const BannerActions = () => {
	return (
		<div className="discoverability-card__actions">
			<Button href="#" isPrimary>
				{ __( 'Learn More', 'woocommerce-payments' ) }
			</Button>
			<p>{ __( 'Remind me later', 'woocommerce-payments' ) }</p>
			<p>{ __( "Don't show me this again", 'woocommerce-payments' ) }</p>
		</div>
	);
};

export default BannerActions;
