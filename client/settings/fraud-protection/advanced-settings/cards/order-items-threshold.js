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

const OrderItemsThresholdRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Order Items Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the amount of items in an order to the minimum and maximum counts that you specify.',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				key={ 'order_items_threshold' }
				label={ __(
					'Screen transactions for abnormal item counts',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			></FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'An unusually high item count, compared to the average for your business, can indicate potential fraudulent activity.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export default OrderItemsThresholdRuleCard;
