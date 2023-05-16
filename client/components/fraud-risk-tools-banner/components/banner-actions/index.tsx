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
				href="https://woocommerce.com/document/woocommerce-payments/fraud-and-disputes/fraud-protection/"
				target="_blank"
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
