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
			progressiveOnboarding: { isComplete, isEnabled },
		},
		onboardingTestMode,
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

	return ! isDismissed && ! onboardingTestMode ? (
		<Card className="wcpay-connection-success">
			{ /* Show dismiss button only at the end of Progressive Onboarding //
			or at the end of the full KYC flow. */ }
			{ ! ( isEnabled && ! isComplete ) && <DismissMenu /> }
			<img src={ ConfettiImage } alt="confetti" />
			{ isEnabled && ! isComplete ? (
				<>
					<h2>
						{ __(
							"You're ready to start selling!",
							'woocommerce-payments'
						) }
					</h2>
					<p>
						{ __(
							'Congratulations! Take a moment to celebrate and look out for the first sale.',
							'woocommerce-payments'
						) }
					</p>
				</>
			) : (
				<>
					<h2>
						{ __(
							'Congratulations! Your store has been verified.',
							'woocommerce-payments'
						) }
					</h2>
				</>
			) }
		</Card>
	) : null;
};

export default ConnectionSuccessNotice;
