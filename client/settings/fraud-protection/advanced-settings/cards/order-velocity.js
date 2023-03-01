/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';

import './../../style.scss';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';

const OrderVelocityRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Order Velocity', 'woocommerce-payments' ) }
		description={ __(
			'This filter screens for excessive repeat orders from the same IP address or payment card.',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				key={ 'order_velocity' }
				label={ __(
					'Screen transactions for excessive repeat orders',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			></FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'Fraudsters often submit multiple purchases using an automated script that tests unknown card numbers. ' +
						'Alternatively, the fraudster may attempt to bypass other filters by making multiple small purchases ' +
						'with multiple stolen account numbers.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export default OrderVelocityRuleCard;
