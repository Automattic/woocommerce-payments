/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleToggle from '../rule-toggle';
import AmountInput from 'wcpay/components/amount-input';
import FraudProtectionRuleCardNotice from '../rule-card-notice';

const PurchasePriceThresholdCustomForm = () => {
	const [ minAmount, setMinAmount ] = useState( '' );
	const [ maxAmount, setMaxAmount ] = useState( '' );
	return (
		<div className="fraud-protection-rule-toggle-children-container">
			<strong>Limits</strong>
			<div className="fraud-protection-rule-toggle-children-horizontal-form">
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-minimum">
						{ __(
							'Minimum Purchase Price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-minimum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ minAmount }
						onChange={ ( val ) => {
							setMinAmount( val );
						} }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-maximum">
						{ __(
							'Maximum Purchase Price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-maximum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ maxAmount }
						onChange={ ( val ) => {
							setMaxAmount( val );
						} }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
			{ '' === minAmount && '' === maxAmount && (
				<div>
					<br />
					<FraudProtectionRuleCardNotice type={ 'warning' }>
						{ __(
							'A price range must be set for this filter to take effect.',
							'woocommerce-payments'
						) }
					</FraudProtectionRuleCardNotice>
				</div>
			) }
			{ parseFloat( minAmount ) > parseFloat( maxAmount ) ? (
				<div>
					<br />
					<FraudProtectionRuleCardNotice type={ 'error' }>
						{ __(
							'Maximum purchase price must be greater than the minimum purchase price.',
							'woocommerce-payments'
						) }
					</FraudProtectionRuleCardNotice>
				</div>
			) : null }
		</div>
	);
};

const PurchasePriceThresholdRuleCard = () => (
	<FraudProtectionRuleCard
		title={ __( 'Purchase Price Threshold', 'woocommerce-payments' ) }
		description={ __(
			'This filter compares the purchase price of an order to the minimum and maximum purchase amounts that you specify.',
			'woocommerce-payments'
		) }
	>
		<div>
			<FraudProtectionRuleToggle
				key={ 'purchase_price_threshold' }
				label={ __(
					'Screen transactions for abnormal purchase prices',
					'woocommerce-payments'
				) }
				helpText={ __(
					'When enabled, the payment method will not be charged until you review and approve the transaction'
				) }
			>
				<PurchasePriceThresholdCustomForm />
			</FraudProtectionRuleToggle>
			<FraudProtectionRuleDescription>
				{ __(
					'An unusually high purchase amount, compared to the average for your business, ' +
						'can indicate potential fraudulent activity.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleDescription>
		</div>
	</FraudProtectionRuleCard>
);

export default PurchasePriceThresholdRuleCard;
