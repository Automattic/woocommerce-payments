/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, DropdownMenu } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ConfettiImage from '../../assets/images/confetti.svg';

const ConnectionSuccessNotice: React.FC = () => {
	const [ isDismissed, setIsDismissed ] = React.useState( false );

	const {
		accountStatus: {
			progressiveOnboarding: {
				isEnabled: isPoEnabled,
				isComplete: isPoComplete,
			},
			status: accountStatus,
		},
		testModeOnboarding,
	} = wcpaySettings;

	const DismissMenu = () => {
		return (
			<DropdownMenu
				className="wcpay-connection-success__dropdown"
				label={ __( 'Dismiss element', 'woocommerce-payments' ) }
				icon="ellipsis"
				controls={ [
					{
						icon: 'button',
						title: __( 'Dismiss', 'woocommerce-payments' ),
						onClick: () => setIsDismissed( true ),
					},
				] }
			/>
		);
	};
	const isPoDisabledOrCompleted = ! isPoEnabled || isPoComplete;
	return ! isDismissed && ! testModeOnboarding && isPoDisabledOrCompleted ? (
		<Card className="wcpay-connection-success">
			<DismissMenu />
			<img src={ ConfettiImage } alt="confetti" />
			{ accountStatus !== 'complete' ? (
				<h2>
					{ __(
						'Congratulations! Your store is being verified.',
						'woocommerce-payments'
					) }
				</h2>
			) : (
				<h2>
					{ __(
						'Congratulations! Your store has been verified.',
						'woocommerce-payments'
					) }
				</h2>
			) }
		</Card>
	) : null;
};

export default ConnectionSuccessNotice;
