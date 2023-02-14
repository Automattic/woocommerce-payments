/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { ProtectionLevels } from './components';
import './style.scss';

const FraudProtection = () => {
	return (
		<Card className="fraud-protection">
			<CardBody>
				<h4>{ __( 'Payment risk level', 'woocommerce-payments' ) }</h4>
				<ProtectionLevels />
			</CardBody>
		</Card>
	);
};

export default FraudProtection;
