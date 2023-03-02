/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { SelectControl, TextControl } from '@wordpress/components';
/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';

const OrderVelocityCustomForm = () => {
	return (
		<div className="fraud-protection-rule-toggle-children-container">
			<strong>{ __( 'Limits', 'woocommerce-payments' ) }</strong>
			<div className="fraud-protection-rule-toggle-children-horizontal-form">
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-order-velocity-threshold">
						{ __( 'Maximum Orders', 'woocommerce-payments' ) }
					</label>
					<TextControl
						id={ 'fraud-protection-order-velocity-threshold' }
						value={ '1' }
						type={ 'number' }
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-order-velocity-interval">
						{ __( 'Every', 'woocommerce-payments' ) }
					</label>
					<SelectControl
						id="fraud-protection-order-velocity-interval"
						options={ [
							{
								label: __( '12 hours', 'woocommerce-payments' ),
								value: 12,
							},
							{
								label: __( '24 hours', 'woocommerce-payments' ),
								value: 24,
							},
							{
								label: __( '48 hours', 'woocommerce-payments' ),
								value: 48,
							},
							{
								label: __( '72 hours', 'woocommerce-payments' ),
								value: 72,
							},
						] }
					/>
				</div>
			</div>
		</div>
	);
};

const OrderVelocityRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Order Velocity', 'woocommerce-payments' ) }
		description={ __(
			'This filter screens for excessive repeat orders from the same IP address or payment card.',
			'woocommerce-payments'
		) }
	>
		<FraudProtectionRuleToggle
			key={ 'order_velocity' }
			label={ __(
				'Screen transactions for excessive repeat orders',
				'woocommerce-payments'
			) }
			helpText={ __(
				'When enabled, the payment method will not be charged until you review and approve the transaction'
			) }
		>
			<OrderVelocityCustomForm />
		</FraudProtectionRuleToggle>
		<FraudProtectionRuleDescription>
			{ __(
				'Fraudsters often submit multiple purchases using an automated script that tests unknown card numbers. ' +
					'Alternatively, the fraudster may attempt to bypass other filters by making multiple small purchases ' +
					'with multiple stolen account numbers.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleDescription>
	</FraudProtectionRuleCard>
);

export default OrderVelocityRuleCard;
