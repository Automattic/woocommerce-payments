/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Card } from 'wcpay/components/card';
import CardBody from '../card-body';
import ProtectionLevels from './protection-levels';
import FraudProtectionTour from './tour';

const FraudProtection: React.FC = () => {
	return (
		<>
			<Card className="fraud-protection">
				<CardBody className="wcpay-card-body">
					<h4>
						{ __(
							'Set your payment risk level',
							'woocommerce-payments'
						) }
					</h4>
					<ProtectionLevels />
				</CardBody>
			</Card>

			<FraudProtectionTour />
		</>
	);
};

export default FraudProtection;
