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
	return (
		<Card className="wcpay-connection-success">
			<img src={ ConfettiImage } alt="confetti" />
			<h2>
				{ __(
					"Congratulations, you're ready to accept payments. Happy selling!",
					'woocommerce-payments'
				) }
			</h2>
		</Card>
	);
};

export default ConnectionSuccessNotice;
