/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const BannerActions = ( {
	remindMeCount,
	handleRemindOnClick,
	handleDontShowAgainOnClick,
} ) => {
	return (
		<div className="discoverability-card__actions">
			<Button href="#" isPrimary>
				{ __( 'Learn More', 'woocommerce-payments' ) }
			</Button>
			<Button isTertiary onClick={ handleRemindOnClick }>
				{ __( 'Remind me later', 'woocommerce-payments' ) }
			</Button>
			{ 3 === remindMeCount && (
				<Button isTertiary onClick={ handleDontShowAgainOnClick }>
					{ __( "Don't show me this again", 'woocommerce-payments' ) }
				</Button>
			) }
		</div>
	);
};

export default BannerActions;
