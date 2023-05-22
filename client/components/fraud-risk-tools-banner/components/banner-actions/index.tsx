/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';

interface BannerActionsProps {
	remindMeCount: number;
	handleRemindOnClick: () => void;
	handleDontShowAgainOnClick: () => void;
}

const BannerActions: React.FC< BannerActionsProps > = ( {
	remindMeCount,
	handleRemindOnClick,
	handleDontShowAgainOnClick,
} ) => {
	const handleLearnMoreButtonClick = () => {
		wcpayTracks.recordEvent(
			'wcpay_fraud_protection_banner_learn_more_button_clicked',
			{}
		);
	};

	return (
		<div className="discoverability-card__actions">
			<Button
				href="/wp-admin/admin.php?page=wc-settings&tab=checkout&anchor=%23fp-settings&section=woocommerce_payments/"
				isPrimary
				onClick={ handleLearnMoreButtonClick }
			>
				{ __( 'Learn more', 'woocommerce-payments' ) }
			</Button>
			{ 3 > remindMeCount ? (
				<Button isTertiary onClick={ handleRemindOnClick }>
					{ __( 'Remind me later', 'woocommerce-payments' ) }
				</Button>
			) : (
				<Button isTertiary onClick={ handleDontShowAgainOnClick }>
					{ __( 'Dismiss', 'woocommerce-payments' ) }
				</Button>
			) }
		</div>
	);
};

export default BannerActions;
