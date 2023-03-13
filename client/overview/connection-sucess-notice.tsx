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
							'Your store is ready to start selling!',
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
				<h2>
					{ __(
						'Your store has been fully verified!',
						'woocommerce-payments'
					) }
				</h2>
			) }
		</Card>
	);
};

export default ConnectionSuccessNotice;
