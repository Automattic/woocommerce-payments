/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';

const BannerActions = ( {
	remindMeCount,
	handleRemindOnClick,
	handleDontShowAgainOnClick,
} ) => {
	return (
		<div className="discoverability-card__actions">
			<Button
				href="/wp-admin/admin.php?page=wc-settings&tab=checkout&anchor=%23fp-settings&section=woocommerce_payments/"
				isPrimary
				onClick={ () => {
					wcpayTracks.recordEvent(
						'wcpay_fraud_protection_banner_learn_more_button_clicked'
					);
				} }
			>
				{ __( 'Learn more', 'woocommerce-payments' ) }
			</Button>
			<Button isTertiary onClick={ handleRemindOnClick }>
				{ __( 'Remind me later', 'woocommerce-payments' ) }
			</Button>
			{ 3 <= remindMeCount && (
				<Button isTertiary onClick={ handleDontShowAgainOnClick }>
					{ __( "Don't show me this again", 'woocommerce-payments' ) }
				</Button>
			) }
		</div>
	);
};

export default BannerActions;
