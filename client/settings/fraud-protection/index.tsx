/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { WPCard } from 'hack-week-2024-components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { ProtectionLevels } from './components';
import './style.scss';
import FraudProtectionTour from './tour';

const FraudProtection: React.FC = () => {
	return (
		<>
			<WPCard className="fraud-protection">
				<CardBody>
					<h4>
						<span id="fraud-protection-card-title">
							{ __(
								'Set your payment risk level',
								'woocommerce-payments'
							) }
						</span>
					</h4>
					<ProtectionLevels />
				</CardBody>
			</WPCard>

			<FraudProtectionTour />
		</>
	);
};

export default FraudProtection;
