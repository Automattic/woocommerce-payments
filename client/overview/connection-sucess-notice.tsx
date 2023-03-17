/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ConfettiImage from '../../assets/images/confetti.svg';
import { Card } from '@wordpress/components';

const ConnectionSuccessNotice: React.FC = () => {
	const {
		accountStatus: {
			progressiveOnboarding: { isComplete, isEnabled },
		},
	} = wcpaySettings;

	return (
		<Card className="wcpay-connection-success">
			<img src={ ConfettiImage } alt="confetti" />
			{ isEnabled && ! isComplete ? (
				<>
					<h2>
						{ __(
							"Congratulations, you're ready to accept payments. Happy selling!",
							'woocommerce-payments'
						) }
					</h2>
					<p>
						{ __(
							'Take a moment to celebrate and look out for the first sale.',
							'woocommerce-payments'
						) }
					</p>
				</>
			) : (
				<>
					<h2>
						{ __(
							'Your store fully verified now!',
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
			) }
		</Card>
	);
};

export default ConnectionSuccessNotice;
