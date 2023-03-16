/**
 * External dependencies
 */
import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import AmountInput from 'wcpay/components/amount-input';

/**
 * Internal dependencies
 */
import FraudProtectionRuleCard from '../rule-card';
import FraudProtectionRuleToggle from '../rule-toggle';
import FraudProtectionRuleDescription from '../rule-description';
import FraudProtectionRuleCardNotice from '../rule-card-notice';
import FraudPreventionSettingsContext from '../context';

const getFloatValue = ( value ) => {
	return '' === value || '0' === value ? 0 : parseFloat( value );
};

const PurchasePriceThresholdCustomForm = ( { setting } ) => {
	const {
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	} = useContext( FraudPreventionSettingsContext );

	const [ minAmount, setMinAmount ] = useState(
		advancedFraudProtectionSettings[ setting ].min_amount ?? ''
	);
	const [ maxAmount, setMaxAmount ] = useState(
		advancedFraudProtectionSettings[ setting ].max_amount ?? ''
	);

	useEffect( () => {
		advancedFraudProtectionSettings[ setting ].min_amount = minAmount;
		advancedFraudProtectionSettings[ setting ].max_amount = maxAmount;
		setAdvancedFraudProtectionSettings( advancedFraudProtectionSettings );
	}, [
		setting,
		minAmount,
		maxAmount,
		advancedFraudProtectionSettings,
		setAdvancedFraudProtectionSettings,
	] );

	const areInputsEmpty =
		! getFloatValue( minAmount ) && ! getFloatValue( maxAmount );
	const isMinGreaterThanMax =
		minAmount &&
		maxAmount &&
		getFloatValue( minAmount ) > getFloatValue( maxAmount );

	return (
		<div className="fraud-protection-rule-toggle-children-container">
			<strong>Limits</strong>
			<div className="fraud-protection-rule-toggle-children-horizontal-form">
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-minimum">
						{ __(
							'Minimum purchase price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-minimum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ minAmount }
						onChange={ setMinAmount }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
				<div className="fraud-protection-rule-toggle-children-vertical-form">
					<label htmlFor="fraud-protection-purchase-price-maximum">
						{ __(
							'Maximum purchase price',
							'woocommerce-payments'
						) }
					</label>
					<AmountInput
						id={ 'fraud-protection-purchase-price-maximum' }
						prefix={ '$' }
						placeholder={ '0.00' }
						value={ maxAmount }
						onChange={ setMaxAmount }
						help={ __(
							'Leave blank for no limit',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
			{ areInputsEmpty && (
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
			{ isMinGreaterThanMax ? (
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
		<FraudProtectionRuleToggle
			setting={ 'purchase_price_threshold' }
			label={ __(
				'Screen transactions for abnormal purchase prices',
				'woocommerce-payments'
			) }
			helpText={ __(
				'When enabled, the payment method will not be charged until you review and approve the transaction'
			) }
		>
			<PurchasePriceThresholdCustomForm
				setting={ 'purchase_price_threshold' }
			/>
		</FraudProtectionRuleToggle>
		<FraudProtectionRuleDescription>
			{ __(
				'An unusually high purchase amount, compared to the average for your business, ' +
					'can indicate potential fraudulent activity.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleDescription>
	</FraudProtectionRuleCard>
);

export const PurchasePriceThresholdValidation = (
	{ enabled, min_amount: minAmount, max_amount: maxAmount },
	setValidationError
) => {
	const minAmountFloat = getFloatValue( minAmount );
	const maxAmountFloat = getFloatValue( maxAmount );
	if ( enabled ) {
		console.log(
			minAmount,
			minAmountFloat,
			maxAmount,
			maxAmountFloat,
			minAmountFloat > maxAmountFloat
		);

		if ( ! minAmountFloat && ! maxAmountFloat ) {
			setValidationError(
				__(
					'A price range must be set for the "Purchase Price threshold" filter.',
					'woocommerce-payments'
				)
			);
			return false;
		}

		if ( minAmount && maxAmount && minAmountFloat > maxAmountFloat ) {
			setValidationError(
				__(
					'Maximum purchase price must be greater than the minimum purchase price.',
					'woocommerce-payments'
				)
			);
			return false;
		}
	}
	return true;
};

export default PurchasePriceThresholdRuleCard;
